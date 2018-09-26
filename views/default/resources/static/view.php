<?php

use Elgg\EntityNotFoundException;
use Elgg\EntityPermissionsException;

$guid = (int) elgg_extract('guid', $vars);

$entity = elgg_call(ELGG_IGNORE_ACCESS, function () use ($guid) {
	return get_entity($guid);
});
if (!$entity instanceof StaticPage) {
	throw new EntityNotFoundException();
}

if (!has_access_to_entity($entity) && !$entity->canEdit()) {
	throw new EntityPermissionsException();
}

// since static has 'magic' URLs make sure context is correct
elgg_set_context('static');

if ($entity->canEdit()) {
	elgg_register_menu_item('title', [
		'name' => 'edit',
		'href' => elgg_generate_entity_url($entity, 'edit'),
		'text' => elgg_echo('edit'),
		'link_class' => 'elgg-button elgg-button-action',
	]);
		
	elgg_register_menu_item('title', [
		'name' => 'create_subpage',
		'text' => elgg_echo('static:add:subpage'),
		'href' => elgg_generate_url('add:object:static', [
			'container_guid' => $entity->owner_guid,
			'parent_guid' => $entity->guid,
		]),
		'link_class' => 'elgg-button elgg-button-action',
	]);
}

// page owner (for groups)
$owner = $entity->getOwnerEntity();
if ($owner instanceof ElggGroup) {
	elgg_set_page_owner_guid($owner->guid);
}

// show breadcrumb
elgg_call(ELGG_IGNORE_ACCESS, function() use ($entity) {
	$parent_entity = $entity->getParentPage();
	if (!$parent_entity) {
		return;
	}
	
	$parents = [];
	while ($parent_entity) {
		$parents[] = $parent_entity;
		$parent_entity = $parent_entity->getParentPage();
	}
	
	// correct order
	$parents = array_reverse($parents);
	/* @var $parent StaticPage */
	foreach ($parents as $parent) {
		elgg_push_breadcrumb($parent->getDisplayName(), $parent->getURL());
	}
});

$ia = elgg_set_ignore_access($entity->canEdit());

// build content
$title = $entity->getDisplayName();

$body = elgg_view_entity($entity, [
	'full_view' => true,
]);

// build sub pages menu
static_setup_page_menu($entity);

// build page
$page = elgg_view_layout('content', [
	'title' => $title,
	'content' => $body,
	'filter' => false,
	'entity' => $entity,
]);

elgg_set_ignore_access($ia);

// draw page
echo elgg_view_page($title, $page);
