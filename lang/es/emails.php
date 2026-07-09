<?php

declare(strict_types=1);

return [
    'subject' => 'Bienvenido a la plataforma',
    'title' => 'Bienvenido',
    'greeting' => 'Hola :name,',
    'intro' => 'Hemos generado con éxito su cuenta y a continuación, encontrará sus credenciales de acceso:',
    'email_label' => 'Correo electrónico',
    'password_label' => 'Contraseña',
    'login_button' => 'Iniciar sesión',
    'login_url_label' => 'O acceda directamente desde:',
    'footer_notice_timken' => 'Este correo fue enviado desde una dirección no monitoreada. Si tiene dudas con respecto a su contenido, póngase en contacto al siguiente correo:',
    'footer_notice_non_timken' => 'Este correo fue enviado desde una dirección no monitoreada. Si tiene dudas con respecto a su contenido, póngase en contacto con su ejecutivo de Customer Service.',
    'footer_rights' => 'Todos los derechos reservados.',

    // Batch finished
    'batch_subject_completed' => 'Batch #:id completado',
    'batch_subject_errors'    => 'Batch #:id finalizado con errores',
    'batch_title'             => 'Resultado de procesamiento',
    'batch_greeting'          => 'Hola :name,',
    'batch_intro'             => 'El batch #:id de :type ha finalizado, el estatus general es',
    'batch_intro_suffix'      => 'y sus detalles se muestran a continuación:',
    'batch_status_completed'  => 'Completado',
    'batch_status_errors'     => 'Finalizado con errores',
    'batch_total'             => 'Total de registros',
    'batch_processed'         => 'Procesados exitosamente',
    'batch_errors'            => 'Con error',
    'batch_processing'        => 'En procesamiento',
    'batch_auto_notice'       => 'Este es un correo automático, por favor no responder.',
    // Users batch welcome
    'batch_users_subject'     => 'Usuarios creados en batch #:id',
    'batch_users_title'       => 'Usuarios creados',
    'batch_users_intro'       => 'Se crearon :count usuarios en el batch #:id. Estas son sus credenciales de acceso:',
    'batch_users_name_label'  => 'Nombre',
    'batch_users_role_label'  => 'Rol',
    'batch_users_auto_notice' => 'Este correo contiene credenciales de usuarios creados por carga masiva. Por favor no responder.',

    // Request pending approval
    'pending_approval_subject'        => 'Tienes una solicitud pendiente de aprobación',
    'pending_approval_title'          => 'Solicitud pendiente de aprobación',
    'pending_approval_greeting'       => 'Hola, :name',
    'pending_approval_intro'          => 'Tienes una nueva solicitud pendiente de aprobación. A continuación encontrarás los detalles:',
    'pending_approval_number_label'   => 'Número de solicitud',
    'pending_approval_type_label'     => 'Tipo de solicitud',
    'pending_approval_class_label'    => 'Clasificación',
    'pending_approval_cta'            => 'Por favor ingresa al sistema para revisar y aprobar la solicitud.',
    'pending_approval_footer'         => 'Este es un mensaje automático, por favor no respondas a este correo.',
    'pending_approval_override_notice'=> 'Este correo se generó desde el ambiente de pruebas. En ambiente productivo este correo hubiese sido enviado a :recipient',

    // Pending approval reminder (multiple requests)
    'reminder_subject'         => 'Recordatorio: tienes solicitudes pendientes por aprobar',
    'reminder_subject_approve'  => 'Tienes solicitudes pendientes de aprobación',
    'reminder_subject_reject'   => 'Tienes solicitudes rechazadas pendientes de revisión',
    'reminder_subject_sendback' => 'Tienes solicitudes regresadas pendientes de revisión',
    'reminder_title'           => 'Solicitudes pendientes de aprobación',
    'reminder_greeting'        => 'Hola, :name',
    'reminder_intro'           => 'Te recordamos que tienes las siguientes solicitudes pendientes por aprobar:',
    'reminder_number_label'    => 'Número de solicitud',
    'reminder_type_label'      => 'Tipo',
    'reminder_class_label'     => 'Clasificación',
    'reminder_cta'             => 'Por favor ingresa al sistema para revisarlas y aprobarlas.',
    'reminder_footer'          => 'Este es un mensaje automático de recordatorio, por favor no respondas a este correo.',
    'reminder_override_notice' => 'Este correo se generó desde el ambiente de pruebas. En ambiente productivo este correo hubiese sido enviado a :recipient',
];
