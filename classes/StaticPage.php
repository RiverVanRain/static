<?php

use Elgg\Database\Clauses\OrderByClause;

class StaticPage extends \ElggObject {
	
	const SUBTYPE = 'static';
	
	/**
	 * (non-PHPdoc)
	 * @see ElggObject::initializeAttributes()
	 */
	protected function initializeAttributes() {
		parent::initializeAttributes();
		
		$this->attributes['subtype'] = self::SUBTYPE;
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ElggEntity::getURL()
	 */
	public function getURL() {
		
		// custom url (eg. /my-static-page)
		$url = $this->friendly_title;
		if (!$url) {
			// basic url
			$url = elgg_generate_entity_url($this, 'view');
		}
		
		// normalize the url
		$url = elgg_normalize_url($url);
		
		// allow other to change the url
		$params = [
			'entity' => $this,
		];
		$url = elgg_trigger_plugin_hook('entity:url', $this->getType(), $params, $url);
		
		// normalize the url
		return elgg_normalize_url($url);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see ElggObject::canComment()
	 */
	public function canComment($user_guid = 0, $default = null) {
		
		if ($this->enable_comments !== 'yes') {
			return false;
		}
		
		if (empty($user_guid)) {
			$user_guid = elgg_get_logged_in_user_guid();
		}
		if (!isset($default)) {
			$default = !empty($user_guid);
		}
		
		return parent::canComment($user_guid, $default);
	}
	
	/**
	 * {@inheritDoc}
	 * @see ElggEntity::delete()
	 */
	public function delete($recursive = true) {
		
		// do this here so we can ignore access later
		if (!$this->canDelete()) {
			return false;
		}
		
		// ignore access, so moderators cleanup everything correctly
		return elgg_call(ELGG_IGNORE_ACCESS, function() use ($recursive) {
			
			$result = parent::delete($recursive);
			if ($result === false || $recursive !== true) {
				return $result;
			}
		
			// remove sub pages
			/* @var $batch \ElggBatch */
			$batch = elgg_get_entities([
				'type' => 'object',
				'subtype' => self::SUBTYPE,
				'metadata_name_value_pairs' => [
					'name' => 'parent_guid',
					'value' => $this->guid,
				],
				'limit' => false,
				'batch' => true,
				'batch_inc_offset' => false,
			]);
			
			/* @var $entity \StaticPage */
			foreach ($batch as $entity) {
				$entity->delete($recursive);
			}
			
			return $result;
		});
	}

	/**
	 * Clears the menu cache for this entity
	 *
	 * @return void
	 */
	public function clearMenuCache() {
		$file = new \ElggFile();
		$file->owner_guid = $this->guid;
		$file->setFilename('static_menu_item_cache');
		if ($file->exists()) {
			$file->delete();
		}
	}
	
	/**
	 * Returns a friendly title
	 */
	public function getFriendlyTitle() {
		$result = $this->friendly_title;
		if ($result) {
			return $result;
		}
		
		// this sometimes happens, prefill with new friendly title
		$result = static_make_friendly_title($this->title);
		
		return $result;
	}
	
	/**
	 * Returns the root page of an entity
	 *
	 * @return \StaticPage
	 */
	public function getRootPage() {
		
		// first created relationship is the root entity
		$relations = $this->getEntitiesFromRelationship([
			'type' => 'object',
			'subtype' => StaticPage::SUBTYPE,
			'relationship' => 'subpage_of',
			'limit' => 1,
		]);
		if (!empty($relations)) {
			return $relations[0];
		}
		
		// no relations so toppage
		return $this;
	}
	
	/**
	 * Returns the parent page of an entity
	 *
	 * @return \StaticPage|false
	 */
	public function getParentPage() {
		
		$parent_guid = $this->parent_guid;
		if (empty($parent_guid)) {
			return false;
		}
		
		$parent_entity = get_entity($parent_guid);
		if (!$parent_entity instanceof \StaticPage) {
			return false;
		}
		
		return $parent_entity;
	}
	
	/**
	 * Returns the latest editor entity for this page, or false if there is none
	 *
	 * @return false|\ElggUser
	 */
	public function getLastEditor() {
		$revision = $this->getLastRevision();
		if (empty($revision)) {
			return false;
		}
		
		$user = $revision->getOwnerEntity();
		if (empty($user)) {
			return false;
		}
		
		return $user;
	}
	
	/**
	 * Get the last revision of a static page
	 *
	 * @return false|\ElggAnnotation
	 */
	public function getLastRevision() {
		
		$revisions = elgg_call(ELGG_IGNORE_ACCESS, function() {
			return $this->getAnnotations([
				'annotation_name' => 'static_revision',
				'limit' => 1,
				'order_by' => new OrderByClause('time_created', 'DESC'),
			]);
		});
		if (empty($revisions)) {
			return false;
		}
		
		return $revisions[0];
	}
	
	/**
	 * Is the page out-of-date
	 * This can be influenced using a hook
	 *
	 * @return bool
	 */
	public function isOutOfDate() {
		
		if (!static_out_of_date_enabled()) {
			return false;
		}
		
		$days = (int) elgg_get_plugin_setting('out_of_date_days', 'static');
		$compare_ts = time() - ($days * 24 * 60 * 60);
		
		$params = [
			'entity' => $this,
		];
		$result = ($this->time_updated < $compare_ts);
		
		return (bool) elgg_trigger_plugin_hook('out_of_date:state', 'static', $params, $result);
	}
	
}
