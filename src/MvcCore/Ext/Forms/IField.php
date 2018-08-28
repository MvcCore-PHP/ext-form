<?php

/**
 * MvcCore
 *
 * This source file is subject to the BSD 3 License
 * For the full copyright and license information, please view
 * the LICENSE.md file that are distributed with this source code.
 *
 * @copyright	Copyright (c) 2016 Tom Flídr (https://github.com/mvccore/mvccore)
 * @license		https://mvccore.github.io/docs/mvccore/4.0.0/LICENCE.md
 */

namespace MvcCore\Ext\Forms;

interface IField
{
	const LABEL_SIDE_LEFT = 'left';
	const LABEL_SIDE_RIGHT = 'right';
	
	const AUTOFOCUS_DUPLICITY_EXCEPTION = 0;
	const AUTOFOCUS_DUPLICITY_UNSET_OLD_SET_NEW = 1;
	const AUTOFOCUS_DUPLICITY_QUIETLY_SET_NEW = -1;
}
