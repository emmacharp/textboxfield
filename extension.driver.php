<?php

	/**
	 * @package textboxfield
	 */

	/**
	 * An enhanced text input field.
	 */
	class Extension_TextBoxField extends Extension {
		/**
		 * The name of the field settings table.
		 */
		const FIELD_TABLE = 'tbl_fields_textbox';

		/**
		 * Publish page headers.
		 */
		const PUBLISH_HEADERS = 1;

		/**
		 * What headers have been appended?
		 *
		 * @var integer
		 */
		static protected $appendedHeaders = 0;

		/**
		 * Add headers to the page.
		 */
		static public function appendHeaders($type) {
			if (
				(self::$appendedHeaders & $type) !== $type
				&& class_exists('Administration', false)
				&& Administration::instance() instanceof Administration
				&& Administration::instance()->Page instanceof HTMLPage
			) {
				$page = Administration::instance()->Page;

				if ($type === self::PUBLISH_HEADERS) {
					$page->addStylesheetToHead(URL . '/extensions/textboxfield/assets/textboxfield.publish.css', 'screen', null, false);
					$page->addScriptToHead(URL . '/extensions/textboxfield/assets/textboxfield.publish.js', null, false);
				}

				self::$appendedHeaders |= $type;
			}
		}

		/**
		 * Create tables and configuration.
		 *
		 * @return boolean
		 */
		public function install() {
			Symphony::Database()
				->create(self::FIELD_TABLE)
				->ifNotExists()
				->fields([
					'id' => [
						'type' => 'int(11)',
						'auto' => true,
					],
					'field_id' => 'int(11)',
					'column_length' => [
						'type' => 'int(11)',
						'default' => 75,
					],
					'text_size' => [
						'type' => 'enum',
						'values' => ['single', 'small', 'medium', 'large', 'huge'],
						'default' => 'medium',
					],
					'text_formatter' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
					'text_validator' => [
						'type' => 'varchar(255)',
						'null' => true,
					],
					'text_length' => [
						'type' => 'int(11)',
						'default' => 0,
					],
					'text_cdata' => [
						'type' => 'enum',
						'values' => ['yes', 'no'],
						'default' => 'no',
					],
					'text_handle' => [
						'type' => 'enum',
						'values' => ['yes', 'no'],
						'default' => 'no',
					],
					'handle_unique' => [
						'type' => 'enum',
						'values' => ['yes', 'no'],
						'default' => 'yes',
					],
				])
				->keys([
					'id' => 'primary',
					'field_id' => 'key',
				])
				->execute()
				->success();

			return true;
		}

		/**
		 * Cleanup installation.
		 *
		 * @return boolean
		 */
		public function uninstall() {
			return Symphony::Database()->drop(self::FIELD_TABLE)->ifExists()->execute()->success();
		}

		/**
		 * Update extension from previous releases.
		 *
		 * @see toolkit.ExtensionManager#update()
		 * @param string $previousVersion
		 * @return boolean
		 */
		public function update($previousVersion = false) {
			// Column length:
			if ($this->updateHasColumn('show_full')) {
				$this->updateRemoveColumn('show_full');
			}

			if (!$this->updateHasColumn('column_length')) {
				$this->updateAddColumn('column_length', 'INT(11) UNSIGNED DEFAULT 75 AFTER `field_id`');
			}

			// Text size:
			if ($this->updateHasColumn('size')) {
				$this->updateRenameColumn('size', 'text_size');
			}

			// Text formatter:
			if ($this->updateHasColumn('formatter')) {
				$this->updateRenameColumn('formatter', 'text_formatter');
			}

			// Text validator:
			if ($this->updateHasColumn('validator')) {
				$this->updateRenameColumn('validator', 'text_validator');
			}

			// Text length:
			if ($this->updateHasColumn('length')) {
				$this->updateRenameColumn('length', 'text_length');
			}

			else if (!$this->updateHasColumn('text_length')) {
				$this->updateAddColumn('text_length', 'INT(11) UNSIGNED DEFAULT 0 AFTER `text_formatter`');
			}

			// Text CDATA:
			if (!$this->updateHasColumn('text_cdata')) {
				$this->updateAddColumn('text_cdata', "ENUM('yes', 'no') DEFAULT 'no' AFTER `text_length`");
			}

			// Text handle:
			if (!$this->updateHasColumn('text_handle')) {
				$this->updateAddColumn('text_handle', "ENUM('yes', 'no') DEFAULT 'no' AFTER `text_cdata`");
			}

			// is handle unique:
			if (!$this->updateHasColumn('handle_unique')) {
				$this->updateAddColumn('handle_unique', "ENUM('yes', 'no') NOT NULL DEFAULT 'yes' AFTER `text_handle`");
			}

			// Add handle index to textbox entry tables:
			$textbox_fields = FieldManager::fetch(null, null, 'ASC', 'sortorder', 'textbox');
			foreach($textbox_fields as $field) {
				$table = "tbl_entries_data_" . $field->get('id');

				// Handle length
				if ($this->updateHasIndex('handle', $table)) {
					$this->updateDropIndex('handle', $table);
				}
				$this->updateModifyColumn('handle', 'VARCHAR(1024)', $table);

				// Make sure we have an index on the handle
				if ($this->updateHasColumn('text_handle') && !$this->updateHasIndex('handle', $table)) {
					$this->updateAddIndex('handle', $table, 333);
				}
				
				// Make sure we have a unique key on `entry_id`
				if ($this->updateHasColumn('entry_id', $table) && !$this->updateHasUniqueKey('entry_id', $table)) {
					$this->updateAddUniqueKey('entry_id', $table);
				}
			}

			return true;
		}

		/**
		 * Add a new Index. Note that this does not check to see if an
		 * index already exists.
		 *
		 * @param string $index
		 * @param string $table
		 * @return boolean
		 */
		public function updateAddIndex($index, $table, $limit = null) {
			if ($limit) {
				$col .= '(' . General::intval($limit) . ')';
			}

			return Symphony::Database()
				->alter($table)
				->addIndex([$index => $col])
				->execute()
				->success();
		}

		/**
		 * Check if the given `$table` has the `$index`.
		 *
		 * @param string $index
		 * @param string $table
		 * @return boolean
		 */
		public function updateHasIndex($index, $table) {
			return (boolean)Symphony::Database()
				->showIndex()
				->from($table)
				->where(['Key_name' => $index])
				->execute()
				->variable('Key_name');
		}

		/**
		 * Drop the given `$index` from `$table`.
		 *
		 * @param string $index
		 * @param string $table
		 * @return boolean
		 */
		public function updateDropIndex($index, $table)
		{
			return Symphony::Database()->query("
				ALTER TABLE
					`$table`
				DROP INDEX
					`{$index}`
			");
		}

		/**
		 * Add a new Unique Key. Note that this does not check to see if an
		 * unique key already exists and will remove any existing key on the column.
		 *
		 * @param string $column
		 * @param string $table
		 * @return boolean
		 */
		public function updateAddUniqueKey($column, $table = self::FIELD_TABLE) {
			try {
				Symphony::Database()
					->alter($table)
					->dropKey($column)
					->execute()
					->success();
			} catch (Exception $ex) {
				// ignore
			}

			return Symphony::Database()
				->alter($table)
				->addKey([$column => 'unique'])
				->execute()
				->success();
		}

		/**
		 * Check if the given `$table` has a unique key on `$column`.
		 *
		 * @param string $column
		 * @param string $table
		 * @return boolean
		 */
		public function updateHasUniqueKey($column, $table = self::FIELD_TABLE) {
			$db = Symphony::Configuration()->get('database', 'db');

			return (boolean)Symphony::Database()
				->select(['CONSTRAINT_NAME'])
				->distinct()
				->from(information_schema.TABLE_CONSTRAINTS)
				->where(['CONSTRAINT_SCHEMA' => $db])
				->where(['CONSTRAINT_NAME' => $column])
				->where(['table_name' => $table])
				->where(['constraint_type' => 'unique'])
				->execute()
				->variable('CONSTRAINT_NAME');
		}

		/**
		 * Add a new column to the settings table.
		 *
		 * @param string $column
		 * @param string $type
		 * @return boolean
		 */
		public function updateAddColumn($column, $type, $table = self::FIELD_TABLE) {
			return Symphony::Database()
				->alter($table)
				->add([$column => $type])
				->execute()
				->success();
		}

		/**
		 * Add a new column to the settings table.
		 *
		 * @param string $column
		 * @param string $type
		 * @return boolean
		 */
		public function updateModifyColumn($column, $type, $table = self::FIELD_TABLE) {
			return Symphony::Database()
				->alter($table)
				->modify([$column => $type])
				->execute()
				->success();
		}

		/**
		 * Does the settings table have a column?
		 *
		 * @param string $column
		 * @return boolean
		 */
		public function updateHasColumn($column, $table = self::FIELD_TABLE) {
			return (boolean)Symphony::Database()
				->showColumns()
				->from($table)
				->where(['Field' => $column])
				->execute()
				->variable('Field');
		}

		/**
		 * Remove a column from the settings table.
		 *
		 * @param string $column
		 * @return boolean
		 */
		public function updateRemoveColumn($column, $table = self::FIELD_TABLE) {
			return Symphony::Database()
				->alter($table)
				->drop($column)
				->execute()
				->success();
		}

		/**
		 * Rename a column in the settings table.
		 *
		 * @param string $column
		 * @return boolean
		 */
		public function updateRenameColumn($from, $to, $table = self::FIELD_TABLE) {
			$data = Symphony::Database()
				->showColumns()
				->from($table)
				->where(['Field' => $from])
				->execute()
				->rows();

			$default = null;
			$null = true;

			if (!is_null($data['Default'])) {
				$default = var_export($data['Default'], true);
			} else if ($data['Null'] == 'YES') {
				$null = true;
			} else {
				$null = false;
			}

			return Symphony::Database()
				->alter($table)
				->change($from, [
					$to => [
						'type' => $data['Type'],
						'default' => $default,
						'null' => $null,
					]
				])
				->execute()
				->success();
		}
	}
