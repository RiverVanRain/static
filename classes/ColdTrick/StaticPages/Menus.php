<?php

namespace ColdTrick\StaticPages;

/**
 * Menus
 */
class Menus {


	/**
	 * Orders the items in the static page menu
	 *
	 * @param string         $hook         'prepare'
	 * @param string         $type         'menu:page'
	 * @param ElggMenuItem[] $return_value the menu items
	 * @param array          $params       supplied params
	 *
	 * @return ElggMenuItem[]
	 */
	public static function pageMenuPrepare($hook, $type, $return_value, $params) {
		$static = elgg_extract('static', $return_value);
	
		if (is_array($static)) {
			$return_value['static'] = self::orderMenu($static);
		}
	
		return $return_value;
	}
	
	/**
	 * Recursively orders menu items
	 *
	 * @param array $menu_items array of menu items that need to be sorted
	 *
	 * @return array
	 */
	private static function orderMenu($menu_items) {
	
		if (!is_array($menu_items)) {
			return $menu_items;
		}
		
		$ordered = [];
		foreach($menu_items as $menu_item) {
			$children = $menu_item->getChildren();
			if ($children) {
				$ordered_children = self::orderMenu($children);
				$menu_item->setChildren($ordered_children);
			}
				
			$ordered[$menu_item->getPriority()] = $menu_item;
		}
		ksort($ordered);

		return $ordered;
	}
	
	/**
	 * Add menu items to the admin page menu
	 *
	 * @param string         $hook         'register'
	 * @param string         $type         'menu:owner_block'
	 * @param ElggMenuItem[] $return_value the menu items
	 * @param array          $params       supplied params
	 *
	 * @return ElggMenuItem[]
	 */
	public static function registerAdminPageMenuItems($hook, $type, $return_value, $params) {
		if (!elgg_in_context('admin') || !elgg_is_admin_logged_in()) {
			return;
		}
		
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'static_all',
			'href' => 'static/all',
			'text' => elgg_echo('static:all'),
			'context' => 'admin',
			'parent_name' => 'appearance',
			'section' => 'configure',
		]);
	
		return $return_value;
	}
	
	/**
	 * Add menu items to the owner block menu
	 *
	 * @param string         $hook         'register'
	 * @param string         $type         'menu:owner_block'
	 * @param ElggMenuItem[] $return_value the menu items
	 * @param array          $params       supplied params
	 *
	 * @return ElggMenuItem[]
	 */
	public static function ownerBlockMenuRegister($hook, $type, $return_value, $params) {

		$owner = elgg_extract('entity', $params);
		if (empty($owner) || !elgg_instanceof($owner, 'group')) {
			return;
		}
	
		if (!static_group_enabled($owner)) {
			return;
		}
		
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'static',
			'text' => elgg_echo('static:groups:owner_block'),
			'href' => "static/group/{$owner->getGUID()}",
		]);
	
		return $return_value;
	}
	
	/**
	 * Add menu items to the filter menu
	 *
	 * @param string         $hook         'register'
	 * @param string         $type         'menu:filter'
	 * @param ElggMenuItem[] $return_value the menu items
	 * @param array          $params       supplied params
	 *
	 * @return ElggMenuItem[]
	 */
	public static function filterMenuRegister($hook, $type, $return_value, $params) {
	
		if (!static_out_of_date_enabled()) {
			return;
		}
	
		if (!elgg_in_context('static')) {
			return;
		}
	
		$page_owner = elgg_get_page_owner_entity();
		if (elgg_instanceof($page_owner, 'group')) {
			$return_value[] = \ElggMenuItem::factory([
				'name' => 'all',
				'text' => elgg_echo('all'),
				'href' => "static/group/{$page_owner->getGUID()}",
				'is_trusted' => true,
				'priority' => 100,
			]);
	
			if ($page_owner->canEdit()) {
				$return_value[] = \ElggMenuItem::factory([
					'name' => 'out_of_date_group',
					'text' => elgg_echo('static:menu:filter:out_of_date:group'),
					'href' => "static/group/{$page_owner->getGUID()}/out_of_date",
					'is_trusted' => true,
					'priority' => 250,
				]);
			}
		} else {
			$return_value[] = \ElggMenuItem::factory([
				'name' => 'all',
				'text' => elgg_echo('all'),
				'href' => 'static/all',
				'is_trusted' => true,
				'priority' => 100,
			]);
		}
	
		if (elgg_is_admin_logged_in()) {
			$return_value[] = \ElggMenuItem::factory([
				'name' => 'out_of_date',
				'text' => elgg_echo('static:menu:filter:out_of_date'),
				'href' => 'static/out_of_date',
				'is_trusted' => true,
				'priority' => 200,
			]);
		}
	
		$user = elgg_get_logged_in_user_entity();
		if (!empty($user)) {
			$return_value[] = \ElggMenuItem::factory([
				'name' => 'out_of_date_mine',
				'text' => elgg_echo('static:menu:filter:out_of_date:mine'),
				'href' => "static/out_of_date/{$user->username}",
				'is_trusted' => true,
				'priority' => 300,
			]);
		}
	
		return $return_value;
	}
	
	/**
	 * Add some menu items
	 *
	 * @param string         $hook         the name of the hook
	 * @param string         $type         the type of the hook
	 * @param ElggMenuItem[] $return_value current menu items
	 * @param array          $params       supplied params
	 *
	 * @return ElggMenuItem[]
	 */
	public static function entityMenuRegister($hook, $type, $return_value, $params) {
	
		$entity = elgg_extract('entity', $params);
		if (!elgg_instanceof($entity, 'object', 'static')) {
			return;
		}
	
		// remove menu items
		foreach ($return_value as $index => $menu_item) {
			if (in_array($menu_item->getName(), ['edit'])) {
				unset($return_value[$index]);
			}
		}
	
		if (!$entity->canComment()) {
			return $return_value;
		}
		
		// add comment link
		$return_value[] = \ElggMenuItem::factory([
			'name' => 'comments',
			'text' => elgg_view_icon('speech-bubble'),
			'href' => "{$entity->getURL()}#comments",
			'title' => elgg_echo('comment:this'),
			'priority' => 300,
		]);
	
		return $return_value;
	}
}
