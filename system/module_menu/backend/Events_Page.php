<?php

  ##################################################################
  ### Copyright © 2017—2019 Maxim Rysevets. All rights reserved. ###
  ##################################################################

namespace effcore\modules\menu {
          use \effcore\block;
          use \effcore\markup_simple;
          use \effcore\markup;
          use \effcore\module;
          use \effcore\storage;
          use \effcore\user;
          abstract class events_page {

  static function on_show_block_menu_user($page) {
    $block_menu = null;
    $user = user::current_get();
    if (empty($user->id)) {
      $block_menu = new block('', ['class' => ['menu-user' => 'menu-user']], [
        storage::get('files')->select('trees/user/user_anonymous'),
        new markup_simple('img', [
          'class' => ['avatar' => 'avatar'],
          'alt' => 'avatar',
          'src' => '/'.module::get('page')->path_get().'frontend/images/avatar-anonymous.svgd'
        ])
      ]);
    } else {
      $block_menu = new block('', ['class' => ['menu-user' => 'menu-user']], [
        storage::get('files')->select('trees/user/user_logged_in'),
        new markup('a', ['href' => '/user/'.$user->id],
          new markup_simple('img', [
            'class' => ['avatar' => 'avatar'],
            'alt' => 'avatar',
            'src' => $user->avatar_path_relative ?
                 '/'.$user->avatar_path_relative :
                 '/'.module::get('page')->path_get().'frontend/images/avatar-logged_in.svgd'
          ])
        )
      ]);
    }
    $block_menu->content_tag_name = null;
    return $block_menu;
  }

}}