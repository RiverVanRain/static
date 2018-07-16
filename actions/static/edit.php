<?php

elgg_make_sticky_form('static');

$guid = (int) get_input('guid');
$owner_guid = (int) get_input('owner_guid'); // site or group
$parent_guid = (int) get_input('parent_guid');
$title = get_input('title');

$friendly_title = get_input('friendly_title');
if (empty($guid)) {
	$friendly_title = static_make_friendly_title($title);
}

$description = get_input('description');
$access_id = (int) get_input('access_id', ACCESS_PUBLIC);

$enable_comments = get_input('enable_comments');
$moderators = get_input('moderators');

$remove_icon = (int) get_input('remove_thumbnail');

if (empty($title) || empty($description)) {
	return elgg_error_response(elgg_echo('static:action:edit:error:title_description'));
}

if (empty($friendly_title)) {
	return elgg_error_response(elgg_echo('static:action:edit:error:friendly_title'));
}

$owner = get_entity($owner_guid);
if (!elgg_instanceof($owner, 'group')) {
	$owner = elgg_get_site_entity();
}

$can_write = $owner->canWriteToContainer(0, 'object', 'static');
if ($can_write) {
	$ia = elgg_set_ignore_access(true);
}

if ($guid == $parent_guid) {
	// can't link to self
	$parent_guid = 0;
}

$ia = elgg_set_ignore_access(true);
$parent = get_entity($parent_guid);
elgg_set_ignore_access($ia);

if (!($parent instanceof StaticPage)) {
	$parent_guid = 0;
	unset($parent);
}

if ($can_write) {
	elgg_set_ignore_access($ia);
}

if ($guid) {
	$ia = elgg_set_ignore_access(true);
	$entity = get_entity($guid);
	elgg_set_ignore_access($ia);

	if (!elgg_instanceof($entity, 'object', 'static') || !$entity->canEdit()) {
		return elgg_error_response();
	}
}

$new_entity = false;
if (!$entity) {
	$entity = new \StaticPage();
	$entity->owner_guid = $owner->getGUID();
	$entity->container_guid = $owner->getGUID();
	$entity->access_id = $access_id;
	
	$ia = elgg_set_ignore_access(true);
	if (!$entity->save()) {
		elgg_set_ignore_access($ia);
		
		return elgg_error_response(elgg_echo('actionunauthorized'));
	}
	
	elgg_set_ignore_access($ia);
	
	$entity->parent_guid = $parent_guid;
	
	$new_entity = true;
}

$parent_changed = false;
if ($parent_guid !== $entity->parent_guid) {
	// reset order if moved to another parent
	unset($entity->order);
	$parent_changed = true;
	
	// remove old tree relationships
	remove_entity_relationships($entity->getGUID(), 'subpage_of');
}
	
if (($new_entity || $parent_changed) && $parent) {
	// add new tree relationship
	$entity->addRelationship($parent->getRootPage()->guid, 'subpage_of');
}

// check the children for the correct tree
if ($parent_changed) {
	
	if ($parent) {
		static_check_children_tree($entity, $parent->getRootPage()->guid);
	} else {
		static_check_children_tree($entity);
	}
}

$ia = elgg_set_ignore_access(true);

// validate friendly title for existing entities if changed
if (!$new_entity && ($entity->friendly_title !== $friendly_title)) {
	$friendly_title = static_make_friendly_title($friendly_title, $guid);
	if (empty($friendly_title)) {
		return elgg_error_response(elgg_echo('static:action:edit:error:friendly_title'));
	}
}

// save all the content
$entity->title = $title;
$entity->description = $description;
$entity->access_id = $access_id;

$entity->parent_guid = $parent_guid;
$entity->friendly_title = $friendly_title;
$entity->enable_comments = $enable_comments;
$entity->moderators = $moderators;

$entity->save();

// icon
if ($remove_icon) {
	$entity->deleteIcon();
} elseif ($uploaded_file = elgg_get_uploaded_file('thumbnail')) {
	/* @var $uploaded_file \Symfony\Component\HttpFoundation\File\UploadedFile */
	if (stripos($uploaded_file->getMimeType(), 'image/') !== false) {
		$entity->saveIconFromUploadedFile('thumbnail');
	}
}

$entity->annotate('static_revision', $description);

elgg_set_ignore_access($ia);
elgg_clear_sticky_form('static');

return elgg_ok_response('', elgg_echo('static:action:edit:success'), $entity->getURL());
