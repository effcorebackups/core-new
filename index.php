<?php

namespace effectivecore {

  const format_date     = 'Y-m-d';
  const format_time     = 'H:i:s';
  const format_datetime = 'Y-m-d H:i:s';
  const dir_root        = __DIR__.'/';
  const dir_cache       = __DIR__.'/dynamic/cache/';
  const dir_modules     = __DIR__.'/modules/';
  const dir_system      = __DIR__.'/system/';
  const nl              = "\n";
  const br              = "<br/>";

  require_once('system/module_core/backend/Events__factory.php');
  require_once('system/module_core/backend/Events_Module__factory.php');
  events_module_factory::on_init();

}