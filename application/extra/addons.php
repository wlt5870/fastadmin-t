<?php

return array (
  'autoload' => false,
  'hooks' => 
  array (
    'action_begin' => 
    array (
      0 => 'clicaptcha',
    ),
    'express_query' => 
    array (
      0 => 'express',
    ),
    'user_sidenav_after' => 
    array (
      0 => 'invite',
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