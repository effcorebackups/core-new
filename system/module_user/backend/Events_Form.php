<?php

namespace effectivecore\modules\user {
          use \effectivecore\urls;
          use \effectivecore\messages;
          use \effectivecore\modules\data\db;
          abstract class events_form extends \effectivecore\events_form {

  function on_submit_user_login($page_args, $form_args, $post_args) {
    $db_user = table_user::select_first(['*'], [
      'password_hash' => sha1($post_args['password']),
      'email'         => $post_args['email'],
    ]);
    if (isset($db_user['id'])) {
      session::init($db_user['id']);
      urls::go('/user/'.$db_user['id']);
    } else {
      messages::add_new('Incorrect email or password!', 'error');
    }
  }

  function on_submit_user_n_delete($page_args, $form_args, $post_args) {
    if (!empty($args['user_id']) &&
        !empty($args['op'])) {
      if ($args['op'] == 'Delete' && table_user::delete(['id' => $args['user_id']])) {
        messages::add_new('User with id "'.$args['user_id'].'" was delited.');
        table_session::delete(['user_id' => $args['user_id']]);
      }
    # redirect in any case (on press button 'Cancel' or 'Delete')
      $back_url = urls::$current->args('back', 'query');
      urls::go($back_url ? urldecode($back_url) : '/admin/users');
    }
  }

  function on_submit_user_n_edit($page_args, $form_args, $post_args) {
    if (table_user::update(['password_hash' => sha1($args['password'])], ['id' => $args['user_id']])) {
      messages::add_new('Parameters of user with id = '.$args['user_id'].' was updated.');
    }
  # redirect to back
    $back_url = urls::$current->args('back', 'query');
    urls::go($back_url ? urldecode($back_url) : '/user/'.$args['user_id']);
  }

  function on_submit_user_register($page_args, $form_args, $post_args) {
    if (table_user::select(['id'], ['email' => $args['email']]) == []) {
      $new_user_id = table_user::insert([
        'email'         => $args['email'],
        'password_hash' => sha1($args['password']),
        'created'       => date(format_datetime, time())
      ]);
      session::init($new_user_id);
      urls::go('/user/'.$new_user_id);
    } else {
      messages::add_new('This email is already registered!', 'error');
    }
  }

}}