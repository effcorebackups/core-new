<?php

  ##################################################################
  ### Copyright © 2017—2019 Maxim Rysevets. All rights reserved. ###
  ##################################################################

namespace effcore {
          class field_password extends field_text {

  public $title = 'Password';
  public $attributes = ['data-type' => 'password'];
  public $element_attributes = [
    'type'         => 'password',
    'name'         => 'password',
    'autocomplete' => 'off',
    'required'     => true,
    'minlength'    => 5,
    'maxlength'    => 255
  ];

}}