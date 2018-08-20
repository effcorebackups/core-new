<?php

  ##################################################################
  ### Copyright © 2017—2019 Maxim Rysevets. All rights reserved. ###
  ##################################################################

namespace effcore {
          class storage_files
          implements has_external_cache {

  function select($dpath, $expand_cache = false) {
    $dpath_parts = explode('/', $dpath);
    $group = array_shift($dpath_parts);
    if (isset(static::$data[$group]) == false) static::init($group);
    if (isset(static::$data[$group])) {
      $c_pointer = static::$data[$group];
      foreach ($dpath_parts as $c_part) {
        $c_pointer = &core::arrobj_value_select($c_pointer, $c_part);
        if ($expand_cache && $c_pointer instanceof external_cache) {
          $c_pointer = $c_pointer->external_cache_load();
        }
      }
      return $c_pointer;
    }
  }

  function changes_insert($module_id, $action, $dpath, $value = null, $rebuild = true) {
  # add new action
    $changes_d = data::select('changes') ?: [];
    $changes_d[$module_id]->{$action}[$dpath] = $value;
    data::update('changes', $changes_d, '', ['build' => core::datetime_get()]);
  # prevent opcache work
    static::$changes_dynamic['changes'] = $changes_d;
    if ($rebuild) {
      static::data_cache_rebuild();
    }
  }

  function changes_delete($module_id, $action, $dpath) {
    # @todo: make functionality
  }

  ###########################
  ### static declarations ###
  ###########################

  static public $data_orig;
  static public $data = [];
  static public $changes_dynamic;

  static function not_external_properties_get() {
    return ['id' => 'id'];
  }

  static function init($group) {
    console::log_add('storage', 'init.', 'storage %%_id will be initialized', 'ok', 0, ['id' => $group.' | storage_files']);
    $cache = cache::select('data--'.$group);
    if ($cache) static::$data[$group] = $cache;
    else        static::data_cache_rebuild();
  }

  ##############################
  ### functionality for data ###
  ##############################

  static function data_cache_rebuild() {
  # init original data
    $data_orig = cache::select('data_original');
    if (!$data_orig) {
      static::$data_orig = $data_orig = static::data_static_find();
      cache::update('data_original', $data_orig, '', ['build' => core::datetime_get()]);
    }
  # init dynamic and static changes
    $changes_d = data::select('changes') ?: [];
    $changes_s = isset($data_orig['changes']) ? $data_orig['changes'] : [];
  # apply all changes to original data and get final data
    $data = core::array_clone_deep($data_orig);
    static::data_changes_apply($changes_d, $data);
    static::data_changes_apply($changes_s, $data);
    unset($data['changes']);
  # save cache
    foreach ($data as $c_group => $c_data) {
      static::$data[$c_group] = $c_data;
      foreach (core::arrobj_values_select_recursive($c_data, true) as $c_dpath => &$c_value) {
        if ($c_value instanceof has_external_cache) {
          $c_cache_id = 'data--'.$c_group.'-'.str_replace('/', '-', $c_dpath);
          $c_not_external_properties = array_intersect_key((array)$c_value, $c_value::not_external_properties_get());
          cache::update($c_cache_id, $c_value);
          $c_value = new external_cache(
            $c_cache_id,
            $c_not_external_properties
          );
        }
      }
      cache::update('data--'.$c_group, $c_data);
    }
  }

  static function data_changes_apply($changes, &$data) {
    foreach ($changes as $module_id => $c_module_changes) {
      foreach ($c_module_changes as $c_action_id => $c_changes) {
        foreach ($c_changes as $c_dpath => $c_data) {
          $c_chain = core::dpath_chain_get($data, $c_dpath);
          $c_child_name = array_keys($c_chain)[count($c_chain)-1];
          $c_parent_name = array_keys($c_chain)[count($c_chain)-2];
          $c_child = &$c_chain[$c_child_name];
          $c_parent = &$c_chain[$c_parent_name];
          switch ($c_action_id) {
          # only structured types is supported: array|object
            case 'insert':
              foreach ($c_data as $c_key => $c_value) {
                core::arrobj_value_insert($c_child, $c_key, $c_value);
              }
              break;
          # only scalar types is supported: string|numeric @todo: test bool|null
            case 'update': core::arrobj_value_insert($c_parent, $c_child_name, $c_data); break;
            case 'delete': core::arrobj_child_delete($c_parent, $c_child_name);          break;
          }
        }
      }
    }
  }

  static function data_static_find() {
    $return = [];
    $parsed = [];
    $modules_path = [];
    $files = file::select_recursive(dir_system, '%^.*\\.data$%') +
             file::select_recursive(dir_modules, '%^.*\\.data$%');
    arsort($files);
  # parse each *.data file and collect modules path
    foreach ($files as $c_file) {
      $c_parsed = static::data_to_code($c_file->load(), $c_file);
      $parsed[$c_file->path_relative_get()] = $c_parsed;
      if ($c_file->file_get() == 'module.data' && isset($c_parsed->module->id)) {
        $modules_path[$c_parsed->module->id] = $c_file->dirs_relative_get();
      }
    }
  # build the result
    foreach ($parsed as $c_file_path => $c_parsed) {
    # define the scope (module_id for each *.data file)
      $c_scope = 'system';
      foreach ($modules_path as $c_module_id => $c_module_path) {
        if (strpos($c_file_path, $c_module_path) === 0) {
          $c_scope = $c_module_id;
          break;
        }
      }
    # fill the $return
      foreach ($c_parsed as $c_type => $c_data) {
        if (is_object($c_data)) {
          if ($c_type == 'module') $c_data->path = $modules_path[$c_scope];
          $return[$c_type][$c_scope] = $c_data;
        }
        if (is_array($c_data)) {
          if (!isset($return[$c_type][$c_scope]))
                     $return[$c_type][$c_scope] = [];
          $return[$c_type][$c_scope] += $c_data;
        }
      }
    }
    return $return;
  }

  ###############
  ### parsing ###
  ###############

  static function code_to_data($code, $entity_name = '', $entity_prefix = '  ', $depth = 0) {
    $return = [];
    if ($entity_name) {
      $return[] = str_repeat('  ', $depth-1).($depth ? $entity_prefix : '').$entity_name;
    }
    foreach ($code as $c_key => $c_value) {
      if (is_array ($c_value) && !count($c_value))           continue;
      if (is_object($c_value) && !get_object_vars($c_value)) continue;
      if (is_array ($c_value))     $return[] = static::code_to_data($c_value, $c_key, is_array($code) ? '- ' : '  ', $depth + 1);
      elseif (is_object($c_value)) $return[] = static::code_to_data($c_value, $c_key, is_array($code) ? '- ' : '  ', $depth + 1);
      elseif ($c_value === null)   $return[] = str_repeat('  ', $depth).(is_array($code) ? '- ' : '  ').$c_key.': null';
      elseif ($c_value === false)  $return[] = str_repeat('  ', $depth).(is_array($code) ? '- ' : '  ').$c_key.': false';
      elseif ($c_value === true)   $return[] = str_repeat('  ', $depth).(is_array($code) ? '- ' : '  ').$c_key.': true';
      else                         $return[] = str_repeat('  ', $depth).(is_array($code) ? '- ' : '  ').$c_key.': '.$c_value;
    }
    return implode(nl, $return);
  }

  # ┌─────────────────────╥───────────────────────────────────────────────────────┐
  # │ valid strings       ║ interpretation                                        │
  # ╞═════════════════════╬═══════════════════════════════════════════════════════╡
  # │ root                ║                                                       │
  # │ - name: value       ║ root[name]  = value:null|string|float|integer|boolean │
  # │   name: value       ║ root->name  = value:null|string|float|integer|boolean │
  # │ - =: value          ║ root[value] = value:null|string|float|integer|boolean │
  # │   =: value          ║ root->value = value:null|string|float|integer|boolean │
  # │ - name              ║ root[name]  = new stdClass | […]                      │
  # │   name              ║ root->name  = new stdClass | […]                      │
  # │ - name|classname    ║ root[name]  = new classname                           │
  # │   name|classname    ║ root->name  = new classname                           │
  # │ - name|_empty_array ║ root[name]  = []                                      │
  # │   name|_empty_array ║ root->name  = []                                      │
  # └─────────────────────╨───────────────────────────────────────────────────────┘

  static function data_to_code($data, $file = null) {
    $return = new \stdClass;
    $p = [-1 => &$return];
    $pc_objects = []; # classes with interface 'has_post_constructor'
    $pi_objects = []; # classes with interface 'has_post_init'
    $line_number = 0;
    foreach (explode(nl, str_replace(nl.'!', '', $data)) as $c_line) {
      $line_number++;
    # skip comments
      if (substr(ltrim($c_line, ' '), 0, 1) === '#') continue;
      $matches = [];
      preg_match('%^(?<indent>[ ]*)'.
                   '(?<prefix>- |)'.
                   '(?<name>.+?)'.
                   '(?<delimiter>(?<!\\\\): |(?<!\\\\)\\||$)'.
                   '(?<value>.*)$%S', $c_line, $matches);
      if (strlen($matches['name'])) {
        $c_prefix    = $matches['prefix'];
        $c_depth     = intval(strlen($matches['indent'].$c_prefix) / 2);
        $c_name      = str_replace(['\\:', '\\|'], [':', '|'], $matches['name']);
        $c_delimiter = $matches['delimiter'];
        $c_value     = $matches['value'];
        if ($c_name == '=') $c_name = $c_value;
      # define each value
        if ($c_delimiter == ': ') {
          $c_value = core::string_to_data($c_value);
        } else {
          if ($c_value == '_empty_array') {
            $c_value = [];
          } else {
            $c_class_name = $c_value ? '\\effcore\\'.$c_value : 'stdClass';
            $c_reflection = new \ReflectionClass($c_class_name);
            $c_is_pc = $c_reflection->implementsInterface('\\effcore\\has_post_constructor');
            $c_is_pi = $c_reflection->implementsInterface('\\effcore\\has_post_init');
            if ($c_is_pc) $c_value = core::class_instance_new_get($c_class_name);
            else          $c_value = core::class_instance_new_get($c_class_name, [], true);
            if ($c_is_pc) $pc_objects[] = $c_value;
            if ($c_is_pi) $pi_objects[] = $c_value;
          }
        }
      # add new item to tree
        core::arrobj_value_insert($p[$c_depth-1], $c_name, $c_value);
        $p[$c_depth] = &core::arrobj_value_select($p[$c_depth-1], $c_name);
      # convert parent item to array
        if ($c_prefix == '- ' && !is_array($p[$c_depth-1])) {
          $p[$c_depth-1] = (array)$p[$c_depth-1];
        }
      } else {
        $messages = ['Function: data_to_code', 'Wrong syntax in data at line: '.$line_number];
        if ($file) $messages[] = 'File relative path: '.$file->path_relative_get();
        message::insert(implode(br, $messages), 'error');
      }
    }
  # call the interface dependent functions
    foreach ($pc_objects as $c_object) $c_object->__construct();
    foreach ($pi_objects as $c_object) $c_object->__post_init();
    return $return;
  }

}}