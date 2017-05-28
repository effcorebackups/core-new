<?php

namespace effectivecore {

  # minimal PHP: 5.6.0
  # tested on PHP: 5.6.0
  # tested on PHP: 7.1.0

  const format_date     = 'Y-m-d';
  const format_time     = 'H:i:s';
  const format_datetime = 'Y-m-d H:i:s';
  const dir_root        = __DIR__.'/';
  const dir_cache       = __DIR__.'/dynamic/cache/';
  const dir_modules     = __DIR__.'/modules/';
  const dir_system      = __DIR__.'/system/';
  const nl              = "\n";
  const br              = "<br/>";

  require_once('system/module_core/backend/File.php');
  require_once('system/module_core/backend/Factory.php');
  require_once('system/module_core/backend/Timer__factory.php');
  require_once('system/module_core/backend/Cache__factory.php');
  require_once('system/module_core/backend/File__factory.php');
  spl_autoload_register('\effectivecore\factory::autoload');
  use \effectivecore\url_factory as urls;
  use \effectivecore\token_factory as tokens;
  use \effectivecore\settings_factory as settings;
  use \effectivecore\timer_factory as timer;
  use \effectivecore\console_factory as console;

  # redirect from '/any_path/' to '/any_path'
  if (urls::get_current()->path != '/' && substr(urls::get_current()->path, -1) == '/') {
    urls::go(
      rtrim(urls::get_current()->path, '/')
    );
  }

  ##########################
  ### single entry point ###
  ##########################

  $file_types = [];
  foreach (settings::get('file_types') as $c_types) {
    foreach ($c_types as $c_name => $c_info) {
      $file_types[$c_name] = $c_info;
    }
  }
  $extension = urls::get_current()->get_extension();
  if ($extension) {
    # case for protected files
    if (!empty($file_types[$extension]->protected)) {
      factory::send_header_and_exit('access_denided',
        'Any file with this extension is protected by settings in file_types!'
      );
    }
    # case for media files
    $path = dir_root.ltrim(urls::get_current()->path, '/');
    if (is_file($path) && is_readable($path)) {
      $data = (new file($path))->load();
      if (isset($file_types[$extension]->mime)) header('Content-type: '.$file_types[$extension]->mime, true);
      if (isset($file_types[$extension]->use_tokens)) $data = tokens::replace($data);
      print $data;
      exit();
    }
  }

  # case for page (non file)
  timer::tap('total');
  foreach (events::get()->on_start as $c_info) {
    $c_handler = $c_info->handler;
    timer::tap($c_handler);
    $c_result = call_user_func($c_handler);
    if ($c_result) {
      print $c_result;
    }
    timer::tap($c_handler);
    console::add_log(
      'Call', $c_handler, '-', timer::get_period($c_handler, 0, 1)
    );
  }

}