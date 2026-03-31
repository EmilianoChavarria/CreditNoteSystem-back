# Workflow Conditional Flow Example

Este documento muestra un ejemplo practico de como usar `workflowSteps` y `workflowStepTransitions` para avanzar por pasos segun condiciones de la request.

## Objetivo del flujo

Flujo base:

- requester
- manager
- vp controller
- fin

Regla:

- Si `totalAmount > 150000`, despues de `manager` debe pasar por `vp controller` y luego ir a `fin`.
- Si `totalAmount <= 150000`, se salta `vp controller` y va directo de `manager` a `fin`.

## Transitions: obligatorias o no?

En tu implementacion actual del controlador:

- `transitions` NO es obligatorio en `POST /api/workflowsteps`.
- `transitions` NO es obligatorio en `PUT /api/workflowsteps/{id}`.

Que pasa si no mandas `transitions`:

- En `POST`: el step se crea sin salidas configuradas.
- En `PUT`: si no mandas `transitions`, se conservan las transiciones actuales del step.

Que pasa si mandas `transitions`:

- En `POST`: se crean esas transiciones para el nuevo step.
- En `PUT`: se reemplazan las transiciones salientes actuales del step por las que envies.

Nota importante:

- Si un step no tiene transiciones, el motor no tendra a donde avanzar (`return null`).
- Por eso se recomienda que todos los steps no finales tengan al menos una transicion.

## 1) Definir pasos (workflowSteps)

Supongamos que `workflowId = 10`.

```json
[
  {
    "id": 101,
    "workflowId": 10,
    "stepName": "requester",
    "stepOrder": 1,
    "roleId": 2,
    "isInitialStep": true,
    "isFinalStep": false
  },
  {
    "id": 102,
    "workflowId": 10,
    "stepName": "manager",
    "stepOrder": 2,
    "roleId": 5,
    "isInitialStep": false,
    "isFinalStep": false
  },
  {
    "id": 103,
    "workflowId": 10,
    "stepName": "vp controller",
    "stepOrder": 3,
    "roleId": 8,
    "isInitialStep": false,
    "isFinalStep": false
  },
  {
    "id": 104,
    "workflowId": 10,
    "stepName": "fin",
    "stepOrder": 4,
    "roleId": 2,
    "isInitialStep": false,
    "isFinalStep": true
  }
]
```

## 2) Definir transiciones (workflowStepTransitions)

### Transiciones desde requester

`requester -> manager` (siempre)

```json
{
  "workflowId": 10,
  "fromStepId": 101,
  "toStepId": 102,
  "conditionField": null,
  "conditionOperator": null,
  "conditionValue": null,
  "priority": 1
}
```

### Transiciones desde manager

1. `manager -> vp controller` cuando `totalAmount > 150000`
2. `manager -> fin` cuando `totalAmount <= 150000`

```json
[
  {
    "workflowId": 10,
    "fromStepId": 102,
    "toStepId": 103,
    "conditionField": "totalAmount",
    "conditionOperator": ">",
    "conditionValue": "150000",
    "priority": 1
  },
  {
    "workflowId": 10,
    "fromStepId": 102,
    "toStepId": 104,
    "conditionField": "totalAmount",
    "conditionOperator": "<=",
    "conditionValue": "150000",
    "priority": 2
  }
]
```

### Transiciones desde vp controller

`vp controller -> fin` (siempre)

```json
{
  "workflowId": 10,
  "fromStepId": 103,
  "toStepId": 104,
  "conditionField": null,
  "conditionOperator": null,
  "conditionValue": null,
  "priority": 1
}
```

## 3) Flujo de JSONs (API) paso a paso

### 3.1 Crear step sin transitions (permitido)

`POST /api/workflowsteps`

```json
{
  "workflowId": 10,
  "stepName": "manager",
  "stepOrder": 2,
  "roleId": 5,
  "isInitialStep": false,
  "isFinalStep": false
}
```

Resultado:

- Crea el step.
- No crea registros en `workflowStepTransitions` para ese `fromStepId`.

### 3.2 Crear step con transitions (recomendado)

Cuando creas o actualizas un step, puedes enviar `transitions`.

Ejemplo: crear `manager` con sus dos salidas condicionales.

`POST /api/workflowsteps`

```json
{
  "workflowId": 10,
  "stepName": "manager",
  "stepOrder": 2,
  "roleId": 5,
  "isInitialStep": false,
  "isFinalStep": false,
  "transitions": [
    {
      "toStepId": 103,
      "conditionField": "totalAmount",
      "conditionOperator": ">",
      "conditionValue": "150000",
      "priority": 1
    },
    {
      "toStepId": 104,
      "conditionField": "totalAmount",
      "conditionOperator": "<=",
      "conditionValue": "150000",
      "priority": 2
    }
  ]
}
```

Resultado:

- Crea el step `manager`.
- Crea dos transiciones salientes desde ese step.

### 3.3 Actualizar un step sin mandar transitions

`PUT /api/workflowsteps/102`

```json
{
  "stepName": "manager approver",
  "roleId": 6
}
```

Resultado:

- Actualiza nombre y rol.
- Mantiene las transiciones existentes (no las toca).

### 3.4 Actualizar un step mandando transitions (reemplaza)

`PUT /api/workflowsteps/102`

```json
{
  "transitions": [
    {
      "toStepId": 103,
      "conditionField": "totalAmount",
      "conditionOperator": ">",
      "conditionValue": "150000",
      "priority": 1
    },
    {
      "toStepId": 104,
      "conditionField": null,
      "conditionOperator": null,
      "conditionValue": null,
      "priority": 2
    }
  ]
}
```

Resultado:

- Borra las transiciones salientes anteriores de `fromStepId = 102`.
- Crea las nuevas transiciones enviadas.

### 3.5 Ejemplo de error comun

Si envias `toStepId` de otro `workflowId`, recibes `422`.

Respuesta esperada (ejemplo):

```json
{
  "success": false,
  "message": "Invalid data",
  "data": {
    "transitions": [
      "El toStepId debe pertenecer al mismo workflow"
    ]
  }
}
```

## 4) Ejecucion del flujo (motor de decision)

En cada aprobacion, tomando el step actual, el sistema debe:

1. Leer transiciones de `fromStepId = stepActual.id`.
2. Ordenar por `priority` ascendente.
3. Evaluar cada condicion contra los datos de la request.
4. Tomar la primera que cumpla.
5. Crear/actualizar el siguiente registro en `workflowRequestSteps` o `workflowRequestCurrentStep`.

Pseudocodigo:

```text
transitions = getTransitions(stepActual).orderBy(priority)

for t in transitions:
  if t.conditionField es null:
    return t.toStepId

  valorRequest = request[t.conditionField]
  if comparar(valorRequest, t.conditionOperator, t.conditionValue):
    return t.toStepId

return null  // no hay transicion valida
```

## 5) Resultado esperado segun monto

Caso A: `totalAmount = 200000`

- requester -> manager
- manager -> vp controller (cumple `> 150000`)
- vp controller -> fin

Caso B: `totalAmount = 80000`

- requester -> manager
- manager -> fin (cumple `<= 150000`)

Caso C: `manager` sin transitions

- requester -> manager
- manager -> sin siguiente paso (flujo detenido hasta configurar transitions)

## 6) Recomendaciones

- Mantener `priority` unica por cada `fromStepId` para evitar ambiguedad.
- Definir siempre una salida de respaldo (fallback), por ejemplo una transicion sin condicion.
- Registrar en historial que condicion se evaluo y cual transicion fue tomada.
