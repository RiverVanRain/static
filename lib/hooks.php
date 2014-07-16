<?php
/**
 * All plugin hooks are bundled here
 */

/**
 * Check if requested page is a static page
 *
 * @param string $hook         name of the hook
 * @param string $type         type of the hook
 * @param array  $return_value return value
 * @param array  $params       hook parameters
 *
 * @return array
 */
function static_route_hook_handler($hook, $type, $return_value, $params) {
	/**
	 * $return_value contains:
	 * $return_value['identifier'] => requested handler
	 * $return_value['segments'] => url parts ($page)
	 */

	$identifier = $return_value['identifier'];

	if (!empty($identifier)) {
		$router = _elgg_services()->router;
		$handlers = $router->getPageHandlers();

		if (!elgg_extract($identifier, $handlers)) {
			$options = array(
				"type" => "object",
				"subtype" => "static",
				"limit" => 1,
				"metadata_name_value_pairs" => array("friendly_title" => $identifier),
				"metadata_case_sensitive" => false
			);
			
			$ia = elgg_set_ignore_access(true);
			$entities = elgg_get_entities_from_metadata($options);
			elgg_set_ignore_access($ia);
			
			if (!empty($entities)) {
				$entity = $entities[0];
				if (has_access_to_entity($entity) || $entity->canEdit()) {
					
					$entity_guid = $entities[0]->getGUID();
	
					$return_value['segments'] = array("view", $entity_guid);
					$return_value['identifier'] = "static";
	
					return $return_value;
				}
			}
		}
	}
}

/**
 * Returns a url for a static content page or a static widget
 *
 * @param string $hook         name of the hook
 * @param string $type         type of the hook
 * @param array  $return_value return value
 * @param array  $params       hook parameters
 *
 * @return string
 */
function static_entity_url_hook_handler($hook, $type, $return_value, $params) {
	
	if (empty($params) || !is_array($params)) {
		return $return_value;
	}
	
	$entity = elgg_extract("entity", $params);
	if (elgg_instanceof($entity, "object", "static")) {
		// static pages
		$friendly_title = $entity->friendly_title;
		if ($friendly_title) {
			$return_value = elgg_get_site_url() . $friendly_title;
		} else {
			
			$friendly_title = static_make_friendly_title($entity->title, $entity->getGUID());
			$entity->friendly_title = $friendly_title;

			$return_value = elgg_get_site_url() . $friendly_title;
		}
	} elseif (!$return_value && elgg_instanceof($entity, "object", "widget")) {
		// widgets
		switch ($entity->handler) {
			case "static_groups":
				$return_value = "static/group/" . $entity->getOwnerGUID();
				
				break;
		}
	}
	
	return $return_value;
}

/**
 * Returns a url for a static thumbnail page
 *
 * @param string $hook         name of the hook
 * @param string $type         type of the hook
 * @param array  $return_value return value
 * @param array  $params       hook parameters
 *
 * @return string
 */
function static_entity_icon_url_hook_handler($hook, $type, $return_value, $params) {
	
	if (empty($params) || !is_array($params)) {
		return $return_value;
	}
	
	$entity = elgg_extract("entity", $params);
	if (elgg_instanceof($entity, "object", "static")) {
		$size = elgg_extract("size", $params);
		
		if ($entity->icontime) {
			$return_value = "static/thumbnail/" . $entity->getGUID() . "/" . $size . "/" . $entity->icontime . ".jpg";
		}
	}
	
	return $return_value;
}

/**
 * Allow moderators to edit static pages and their children
 *
 * @param string $hook         'permissions_check'
 * @param string $type         'object'
 * @param bool   $return_value can the user edit this entity
 * @param array  $params       supplied params
 *
 * @return bool
 */
function static_permissions_check_hook_handler($hook, $type, $return_value, $params) {
	
	if (!$return_value && !empty($params) && is_array($params)) {
		$entity = elgg_extract("entity", $params);
		$user = elgg_extract("user", $params);
		
		if (!empty($entity) && elgg_instanceof($entity, "object", "static")) {
			$ia = elgg_set_ignore_access(true);
			// check if the user is a moderator of this static page
			$moderators = $entity->moderators;
			if (!empty($moderators)) {
				if (!is_array($moderators)) {
					$moderators = array($moderators);
				}
				
				$return_value = in_array($user->getGUID(), $moderators);
			}
			
			elgg_set_ignore_access($ia);
			
			// if not moderator, check higher pages (if any)
			if (!$return_value && ($entity->getContainerGUID() != $entity->site_guid)) {
				$moderators = static_get_parent_moderators($entity, true);
				if (!empty($moderators)) {
					$return_value = in_array($user->getGUID(), $moderators);
				}
			}
		}
	}
	
	return $return_value;
}

/**
 * Allow moderators to write static pages
 *
 * @param string $hook         'container_permissions_check'
 * @param string $type         'object'
 * @param bool   $return_value can the user write to this container
 * @param array  $params       supplied params
 *
 * @return bool
 */
function static_container_permissions_check_hook_handler($hook, $type, $return_value, $params) {
	
	if (!empty($type) && $type == "object") {
		
		if (!empty($params) && is_array($params)) {
			$container = elgg_extract("container", $params);
			$subtype = elgg_extract("subtype", $params);
			
			if ($subtype == "static" && elgg_instanceof($container, "site")) {
				$return_value = true;
			} elseif ($subtype == "static" && elgg_instanceof($container, "group") && !$container->canEdit()) {
				$return_value = false;
			}
		}
	}
	
	return $return_value;
}

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
function static_prepare_page_menu_hook_handler($hook, $type, $return_value, $params) {
	$static = elgg_extract("static", $return_value);
	
	if (is_array($static)) {
		$return_value["static"] = static_order_menu($static);
	}
	
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
function static_register_owner_block_menu_hook_handler($hook, $type, $return_value, $params) {
	
	if (!empty($params) && is_array($params)) {
		$owner = elgg_extract("entity", $params);
		
		if (!empty($owner) && elgg_instanceof($owner, "group")) {
			if ($owner->static_enable != "no") {
				$return_value[] = ElggMenuItem::factory(array(
					"name" => "static",
					"text" => elgg_echo("static:groups:owner_block"),
					"href" => "static/group/" . $owner->getGUID()
				));
			}
		}
	}
	
	return $return_value;
}

/**
 *
 * @param string $hook         'entity_types'
 * @param string $type         'content_subscriptions'
 * @param array  $return_value the current supported entity types
 * @param array  $params       supplied params
 *
 * @return array
 */
function static_content_subscriptions_entity_types_handler($hook, $type, $return_value, $params) {
	
	if (!is_array($return_value)) {
		$return_value = array();
	}
	
	if (!isset($return_value["object"])) {
		$return_value["object"] = array();
	}
	
	$return_value["object"][] = "static";
	
	return $return_value;
}

/**
 * Add or remove widgets based on the group tool option
 *
 * @param string $hook         'group_tool_widgets'
 * @param string $type         'widget_manager'
 * @param array  $return_value current enable/disable widget handlers
 * @param array  $params       supplied params
 *
 * @return array
 */
function static_group_tool_widgets_handler($hook, $type, $return_value, $params) {
	
	if (!empty($params) && is_array($params)) {
		$entity = elgg_extract("entity", $params);
		
		if (!empty($entity) && elgg_instanceof($entity, "group")) {
			if (!is_array($return_value)) {
				$return_value = array();
			}
			
			if (!isset($return_value["enable"])) {
				$return_value["enable"] = array();
			}
			if (!isset($return_value["disable"])) {
				$return_value["disable"] = array();
			}
			
			if ($entity->static_enable == "yes") {
				$return_value["enable"][] = "static_groups";
			} else {
				$return_value["disable"][] = "static_groups";
			}
		}
	}
	
	return $return_value;
}

/**
 * Add or remove widgets based on the group tool option
 *
 * @param string $hook         'autocomplete'
 * @param string $type         'search_advanced'
 * @param array  $return_value current search results
 * @param array  $params       supplied params
 *
 * @return array
 */
function static_search_advanced_autocomplete_handler($hook, $type, $return_value, $params) {
	
	if (empty($params) || !is_array($params)) {
		return $return_value;
	}
	
	$query = elgg_extract("query", $params);
	if (empty($query)) {
		return $return_value;
	}
	
	$limit = (int) elgg_extract("limit", $params, 5);
	
	$options = array(
		"type" => "object",
		"subtype" => "static",
		"limit" => $limit,
		"joins" => array("JOIN " . elgg_get_config("dbprefix") . "objects_entity oe ON e.guid = oe.guid"),
		"wheres" => array("(oe.title LIKE '%" . $query . "%' OR oe.description LIKE '%" . $query . "%')")
	);
	$entities = elgg_get_entities($options);
	
	if (!empty($entities)) {
		if (count($entities) >= $limit) {
			$options["count"] = true;
			$static_count = elgg_get_entities($options);
		} else {
			$static_count = count($entities);
		}
		
		$return_value[] = array(
			"type" => "placeholder",
			"content" => "<label>" . elgg_echo("item:object:static") . " (" . $static_count . ")</label>",
			"href" => elgg_normalize_url("search?entity_subtype=static&entity_type=object&search_type=entities&q=" . $query)
		);
		
		foreach ($entities as $entity) {
			$return_value[] = array(
				"type" => "object",
				"value" => $entity->title,
				"href" => $entity->getURL(),
				"content" => elgg_view("static/search_advanced/item", array("entity" => $entity)
			));
		}
	}
	
	return $return_value;
}