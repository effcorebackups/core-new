<?php

  ##################################################################
  ### Copyright © 2017—2018 Maxim Rysevets. All rights reserved. ###
  ##################################################################

namespace effcore {
          abstract class selector {

  static function users_insert($page) {return new text('users_insert is UNDER CONSTRUCTION');}
  static function users_update($page) {return new text('users_update is UNDER CONSTRUCTION');}
  static function users_delete($page) {return new text('users_delete is UNDER CONSTRUCTION');}

  static function users_select($page) {
    $pager = new pager();
    if ($pager->has_error) {
      factory::send_header_and_exit('not_found');
    } else {
      $thead = [['ID', 'EMail', 'Nick', 'Created', 'Is embed', '']];
      $tbody = [];
      foreach (entity::get('user')->select_instances() as $c_user) {
        $c_action_list = new control_actions_list([], [], null);
        $c_action_list->action_add('/user/'.$c_user->id, 'view');
        $c_action_list->action_add('/user/'.$c_user->id.'/edit?'.url::make_back_part(), 'edit');
        $c_action_list->action_add('/admin/users/delete/'.$c_user->id.'?'.url::make_back_part(), 'delete', !$c_user->is_embed);
        $tbody[] = [
          new table_body_row_cell(['class' => ['id' => 'id']], $c_user->id),
          new table_body_row_cell(['class' => ['email' => 'email']], $c_user->email),
          new table_body_row_cell(['class' => ['nick' => 'nick']], $c_user->nick),
          new table_body_row_cell(['class' => ['created' => 'created']], locale::format_datetime($c_user->created)),
          new table_body_row_cell(['class' => ['is_embed' => 'is_embed']], $c_user->is_embed ? 'Yes' : 'No'),
          new table_body_row_cell(['class' => ['actions' => 'actions']], $c_action_list)
        ];
      }
      return new markup('x-block', ['id' => 'users_admin'],
        new table([], $tbody, $thead)
      );
    }
  }

}}