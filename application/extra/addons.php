<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'action_begin' => 
    array (
      0 => 'clicaptcha',
    ),
    'app_init' => 
    array (
      0 => 'epay',
    ),
    'express_query' => 
    array (
      0 => 'express',
    ),
    'user_sidenav_after' => 
    array (
      0 => 'invite',
      1 => 'withdraw',
    ),
    'user_register_successed' => 
    array (
      0 => 'invite',
    ),
    'leesignhook' => 
    array (
      0 => 'leesign',
    ),
    'config_init' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_send' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_notice' => 
    array (
      0 => 'qcloudsms',
    ),
    'sms_check' => 
    array (
      0 => 'qcloudsms',
    ),
    'upload_config_init' => 
    array (
      0 => 'ucloud',
    ),
    'upload_delete' => 
    array (
      0 => 'ucloud',
    ),
  ),
  'route' => 
  array (
    '/invite/[:id]$' => 'invite/index/index',
    '/leesign$' => 'leesign/index/index',
    '/third$' => 'third/index/index',
    '/third/connect/[:platform]' => 'third/index/connect',
    '/third/callback/[:platform]' => 'third/index/callback',
  ),
);