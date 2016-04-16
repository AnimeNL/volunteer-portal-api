<?php
// Copyright 2015 Peter Beverloo. All rights reserved.
// Use of this source code is governed by the MIT license, a copy of which can
// be found in the LICENSE file.

Header('Content-Type: text/css');

?>
/*******************************************************************************
 * style/material.css
 ******************************************************************************/

<?php echo file_get_contents(__DIR__ . '/material.css'); ?>

/*******************************************************************************
 * style/layout.css
 ******************************************************************************/

<?php echo file_get_contents(__DIR__ . '/layout.css'); ?>

/*******************************************************************************
 * style/desktop.css
 ******************************************************************************/

@media (min-width: 768px) {

<?php echo file_get_contents(__DIR__ . '/desktop.css'); ?>

}
