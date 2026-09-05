<?php

declare(strict_types=1);

namespace MOL\Database;

final class Schema
{
    /** @return list<string> */
    public static function statements(string $prefix, string $charsetCollate): array
    {
        $tables = new TableNames($prefix);

        return array(
            "CREATE TABLE {$tables->chapters} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  work_id bigint(20) unsigned NOT NULL,
  chapter_label varchar(64) NOT NULL,
  sort_order decimal(14,4) NOT NULL DEFAULT 0,
  title varchar(255) NULL,
  slug varchar(190) NOT NULL,
  translation_status varchar(24) NOT NULL DEFAULT 'untranslated',
  source_lang_override varchar(255) NULL,
  reader_mode_override varchar(16) NULL,
  direction_override varchar(8) NULL,
  is_published tinyint(1) NOT NULL DEFAULT 0,
  published_at datetime NULL,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_work_slug (work_id, slug),
  KEY idx_work_sort (work_id, sort_order),
  KEY idx_status (translation_status),
  KEY idx_published (is_published, published_at)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->pages} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chapter_id bigint(20) unsigned NOT NULL,
  page_index int(11) unsigned NOT NULL,
  attachment_id bigint(20) unsigned NOT NULL,
  natural_width int(11) unsigned NOT NULL,
  natural_height int(11) unsigned NOT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_chapter_index (chapter_id, page_index),
  KEY idx_chapter (chapter_id),
  KEY idx_attachment (attachment_id)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->elements} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  page_id bigint(20) unsigned NOT NULL,
  target_lang varchar(255) NOT NULL DEFAULT 'ar',
  element_type varchar(24) NOT NULL,
  x_unit int(11) unsigned NOT NULL,
  y_unit int(11) unsigned NOT NULL,
  w_unit int(11) unsigned NOT NULL,
  h_unit int(11) unsigned NOT NULL,
  rotation_mdeg int(11) NOT NULL DEFAULT 0,
  z_index int(11) NOT NULL DEFAULT 0,
  content longtext NOT NULL,
  style_json longtext NOT NULL,
  version bigint(20) unsigned NOT NULL DEFAULT 1,
  created_by bigint(20) unsigned NOT NULL,
  updated_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_page_lang_z (page_id, target_lang, z_index),
  KEY idx_updated_by (updated_by)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->elementLocks} (
  element_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  lock_token char(64) NOT NULL,
  acquired_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (element_id),
  UNIQUE KEY uq_token (lock_token),
  KEY idx_user (user_id),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->contributions} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  element_id bigint(20) unsigned NOT NULL,
  user_id bigint(20) unsigned NOT NULL,
  work_id bigint(20) unsigned NOT NULL,
  chapter_id bigint(20) unsigned NOT NULL,
  created_element tinyint(1) NOT NULL DEFAULT 0,
  first_contributed_at datetime NOT NULL,
  last_contributed_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_element_user (element_id, user_id),
  KEY idx_chapter_user (chapter_id, user_id),
  KEY idx_work_user (work_id, user_id),
  KEY idx_user_last (user_id, last_contributed_at)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->reports} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  chapter_id bigint(20) unsigned NOT NULL,
  page_id bigint(20) unsigned NULL,
  element_id bigint(20) unsigned NULL,
  reporter_id bigint(20) unsigned NOT NULL,
  report_type varchar(24) NOT NULL,
  message text NOT NULL,
  status varchar(24) NOT NULL DEFAULT 'open',
  resolved_by bigint(20) unsigned NULL,
  created_at datetime NOT NULL,
  resolved_at datetime NULL,
  PRIMARY KEY  (id),
  KEY idx_status_created (status, created_at),
  KEY idx_chapter (chapter_id),
  KEY idx_element (element_id)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->readingProgress} (
  user_id bigint(20) unsigned NOT NULL,
  chapter_id bigint(20) unsigned NOT NULL,
  page_index int(11) unsigned NOT NULL DEFAULT 0,
  progress_unit int(11) unsigned NOT NULL DEFAULT 0,
  reader_mode varchar(16) NOT NULL DEFAULT 'webtoon',
  updated_at datetime NOT NULL,
  PRIMARY KEY  (user_id, chapter_id),
  KEY idx_user_updated (user_id, updated_at)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->stylePresets} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  scope varchar(16) NOT NULL,
  owner_user_id bigint(20) unsigned NULL,
  work_id bigint(20) unsigned NULL,
  name varchar(100) NOT NULL,
  element_type varchar(24) NOT NULL,
  style_json longtext NOT NULL,
  is_default tinyint(1) NOT NULL DEFAULT 0,
  created_by bigint(20) unsigned NOT NULL,
  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY idx_scope_work (scope, work_id, element_type),
  KEY idx_owner (owner_user_id, element_type)
) ENGINE=InnoDB {$charsetCollate};",
            "CREATE TABLE {$tables->idempotencyKeys} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  user_id bigint(20) unsigned NOT NULL,
  scope varchar(64) NOT NULL,
  idempotency_key varchar(100) NOT NULL,
  request_hash char(64) NOT NULL,
  resource_type varchar(32) NULL,
  resource_id bigint(20) unsigned NULL,
  response_code int(11) unsigned NULL,
  response_json longtext NULL,
  created_at datetime NOT NULL,
  expires_at datetime NOT NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY uq_user_scope_key (user_id, scope, idempotency_key),
  KEY idx_expires (expires_at)
) ENGINE=InnoDB {$charsetCollate};",
        );
    }

    /** @return array<string, list<string>> */
    public static function requiredIndexes(): array
    {
        return array(
            'mol_chapters' => array('PRIMARY', 'uq_work_slug', 'idx_work_sort', 'idx_status', 'idx_published'),
            'mol_pages' => array('PRIMARY', 'uq_chapter_index', 'idx_chapter', 'idx_attachment'),
            'mol_elements' => array('PRIMARY', 'idx_page_lang_z', 'idx_updated_by'),
            'mol_element_locks' => array('PRIMARY', 'uq_token', 'idx_user', 'idx_expires'),
            'mol_contributions' => array('PRIMARY', 'uq_element_user', 'idx_chapter_user', 'idx_work_user', 'idx_user_last'),
            'mol_reports' => array('PRIMARY', 'idx_status_created', 'idx_chapter', 'idx_element'),
            'mol_reading_progress' => array('PRIMARY', 'idx_user_updated'),
            'mol_style_presets' => array('PRIMARY', 'idx_scope_work', 'idx_owner'),
            'mol_idempotency_keys' => array('PRIMARY', 'uq_user_scope_key', 'idx_expires'),
        );
    }

    /** @return array<string, list<string>> */
    public static function requiredColumns(): array
    {
        return array(
            'mol_chapters' => array(
                'id',
                'work_id',
                'chapter_label',
                'sort_order',
                'title',
                'slug',
                'translation_status',
                'source_lang_override',
                'reader_mode_override',
                'direction_override',
                'is_published',
                'published_at',
                'created_by',
                'created_at',
                'updated_at',
            ),
            'mol_pages' => array(
                'id',
                'chapter_id',
                'page_index',
                'attachment_id',
                'natural_width',
                'natural_height',
                'created_at',
            ),
            'mol_elements' => array(
                'id',
                'page_id',
                'target_lang',
                'element_type',
                'x_unit',
                'y_unit',
                'w_unit',
                'h_unit',
                'rotation_mdeg',
                'z_index',
                'content',
                'style_json',
                'version',
                'created_by',
                'updated_by',
                'created_at',
                'updated_at',
            ),
            'mol_element_locks' => array('element_id', 'user_id', 'lock_token', 'acquired_at', 'expires_at'),
            'mol_contributions' => array(
                'id',
                'element_id',
                'user_id',
                'work_id',
                'chapter_id',
                'created_element',
                'first_contributed_at',
                'last_contributed_at',
            ),
            'mol_reports' => array(
                'id',
                'chapter_id',
                'page_id',
                'element_id',
                'reporter_id',
                'report_type',
                'message',
                'status',
                'resolved_by',
                'created_at',
                'resolved_at',
            ),
            'mol_reading_progress' => array(
                'user_id',
                'chapter_id',
                'page_index',
                'progress_unit',
                'reader_mode',
                'updated_at',
            ),
            'mol_style_presets' => array(
                'id',
                'scope',
                'owner_user_id',
                'work_id',
                'name',
                'element_type',
                'style_json',
                'is_default',
                'created_by',
                'created_at',
                'updated_at',
            ),
            'mol_idempotency_keys' => array(
                'id',
                'user_id',
                'scope',
                'idempotency_key',
                'request_hash',
                'resource_type',
                'resource_id',
                'response_code',
                'response_json',
                'created_at',
                'expires_at',
            ),
        );
    }
}
