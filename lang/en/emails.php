<?php

declare(strict_types=1);

return [
    'subject' => 'Welcome to the platform',
    'title' => 'Welcome',
    'greeting' => 'Hi :name,',
    'intro' => 'We have successfully created your account. Your access credentials are provided below:',
    'email_label' => 'Email address',
    'password_label' => 'Password',
    'login_button' => 'Login',
    'login_url_label' => 'Or access directly at:',
    'footer_notice_timken' => 'This email was sent from an unattended address. If you have any questions about its content, please contact us at the following email:',
    'footer_notice_non_timken' => 'This email was sent from an unattended address. If you have any questions about its content, please contact your assigned customer service representative.',
    'footer_rights' => 'All rights reserved.',

    // Batch finished
    'batch_subject_completed' => 'Batch #:id completed',
    'batch_subject_errors'    => 'Batch #:id finished with errors',
    'batch_title'             => 'Processing result',
    'batch_greeting'          => 'Hi :name,',
    'batch_intro'             => 'Batch #:id of :type has finished, the overall status is',
    'batch_intro_suffix'      => 'and its details are shown below:',
    'batch_status_completed'  => 'Completed',
    'batch_status_errors'     => 'Finished with errors',
    'batch_total'             => 'Total records',
    'batch_processed'         => 'Processed successfully',
    'batch_errors'            => 'With errors',
    'batch_processing'        => 'In processing',
    'batch_auto_notice'       => 'This is an automated email, please do not reply.',
    // Users batch welcome
    'batch_users_subject'     => 'Users created in batch #:id',
    'batch_users_title'       => 'Users created',
    'batch_users_intro'       => ':count users were created in batch #:id. These are their access credentials:',
    'batch_users_name_label'  => 'Name',
    'batch_users_role_label'  => 'Role',
    'batch_users_auto_notice' => 'This email contains credentials for users created by bulk upload. Please do not reply.',

    // Request pending approval
    'pending_approval_subject'        => 'You have a pending approval request',
    'pending_approval_title'          => 'Pending Approval Request',
    'pending_approval_greeting'       => 'Hi, :name',
    'pending_approval_intro'          => 'You have a new pending approval request. Below are the details:',
    'pending_approval_number_label'   => 'Request Number',
    'pending_approval_type_label'     => 'Request Type',
    'pending_approval_class_label'    => 'Classification',
    'pending_approval_cta'            => 'Please log in to the system to review and approve the request.',
    'pending_approval_footer'         => 'This is an automated message, please do not reply to this email.',
    'pending_approval_override_notice'=> 'This email was generated from the test environment. In production, this email would have been sent to :recipient',

    // Pending approval reminder (multiple requests)
    'reminder_subject'         => 'Reminder: you have pending requests awaiting approval',
    'reminder_subject_approve'  => 'You have pending approval requests',
    'reminder_subject_reject'   => 'You have rejected requests awaiting review',
    'reminder_subject_sendback' => 'You have returned requests awaiting review',
    'reminder_title'           => 'Pending Approval Requests',
    'reminder_greeting'        => 'Hi, :name',
    'reminder_intro'           => 'This is a reminder that you have the following requests pending your approval:',
    'reminder_number_label'    => 'Request Number',
    'reminder_type_label'      => 'Type',
    'reminder_class_label'     => 'Classification',
    'reminder_cta'             => 'Please log in to the system to review and approve them.',
    'reminder_footer'          => 'This is an automated reminder, please do not reply to this email.',
    'reminder_override_notice' => 'This email was generated from the test environment. In production, this email would have been sent to :recipient',
];
