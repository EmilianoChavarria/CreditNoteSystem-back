<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bienvenido</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.5; color: #111;">
    <p>Hola {{ $fullName }},</p>
    <p>Tu usuario fue creado correctamente. Estas son tus credenciales:</p>
    <p><strong>Correo:</strong> {{ $email }}<br>
    <strong>Contrasena:</strong> {{ $password }}</p>
    <p>Te recomendamos cambiar tu contrasena despues de iniciar sesion.</p>
    <p>Saludos.</p>
</body>
</html>
