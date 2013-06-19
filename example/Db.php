<?php

/**
 * Little database helper
 */
function db(PDO $db)
{
	$tableHelper = function($table = null) use ($db, &$tableHelper)
	{
		if ($table !== null)
		{
			$update = function($id, $data) use ($db, $table)
			{
				unset($data['id']);

				$query = "UPDATE $table SET " .
					implode(', ', array_map(function($q) { return $q . ' = ?'; }, array_keys($data))) .
					" WHERE id = ? LIMIT 1";

				$updateStatement = $db->prepare($query);
				return $updateStatement->execute(array_merge(array_values($data), [ $id ]));
			};

			$insert = function($data) use ($db, $table)
			{
				$query = "INSERT INTO $table (" .
					implode(', ', array_keys($data)) .
					") VALUES (" . implode(', ', array_fill(0, count($data), '?')) . ")";

				$insertStatement = $db->prepare($query);
				return $insertStatement->execute(array_merge(array_values($data)));
			};

			$find = function($id) use ($db, $table)
			{
				$statement = $db->prepare("SELECT * FROM $table WHERE id = ? LIMIT 1");
				$statement->execute([ $id ]);

				return $statement->fetch(PDO::FETCH_ASSOC);
			};

			$delete = function($id) use ($db, $table)
			{
				$statement = $db->prepare("DELETE FROM $table WHERE id = ? LIMIT 1");
				return $statement->execute([ $id ]);
			};

			$list = function() use ($db, $table)
			{
				$statement = $db->prepare("SELECT * FROM $table");
				$statement->execute();
				return $statement->fetchAll(PDO::FETCH_ASSOC);
			};

			return function($id = null, $data = null) use ($db, $table, $update, $insert, $delete, $find, $list)
			{
				if (is_array($id))
				{
					return $insert($id);
				}
				else if ($id === 'delete' && !empty($data) && ctype_digit((string) $data))
				{
					return $delete($data);
				}
				else if (ctype_digit((string) $id))
				{
					$row = $find($id);

					if ($row !== null && $data !== null)
					{
						$update($id, $data);
					}

					return $row;
				}
				else
				{
					return $list();
				}
			};
		}
		else
		{
			$tables = [];
			$statement = $db->query("SHOW TABLES");

			foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $table)
			{
				$tables[current($table)] = $tableHelper(current($table));
			}

			return $tables;
		}
	};

	return $tableHelper;
}
