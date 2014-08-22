<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
**/


/**
 * Class containing methods for operations with auto-registered hosts.
 *
 * @package API
 */
class CAutoRegHost extends CApiService {

	protected $tableName = 'autoreg_host';
	protected $tableAlias = 'arh';
	protected $sortColumns = array('autoreg_hostid');
	
	/**
	 * Get auto-registered host
	 *
	 * @param array $options
	 * @param array $options['hostids']
	 * @param array $options['proxyids']
	 * 
	 * @return array|boolean host data or false if error
	 */
	public function get($options = array()) {
		$result = array();

		$sqlParts = array(
			'select'		=> array('autoreg_host' => 'arh.autoreg_hostid'),
			'from'			=> array('autoreg_host' => 'autoreg_host arh'),
			'where'			=> array(),
			'group'			=> array(),
			'order'			=> array(),
			'limit'			=> null
		);

		$defOptions = array(
			'hostids'					=> null,
			'proxyids'					=> null,
			// filter
			'filter'					=> null,
			'search'					=> null,
			'searchByAny'				=> null,
			'startSearch'				=> null,
			'excludeSearch'				=> null,
			'searchWildcardsEnabled'	=> null,
			// output
			'output'					=> API_OUTPUT_REFER,
			'countOutput'				=> null,
			'groupCount'				=> null,
			'preservekeys'				=> null,
			'sortfield'					=> '',
			'sortorder'					=> '',
			'limit'						=> null
		);
		$options = zbx_array_merge($defOptions, $options);
		
		// hostids
		if (!is_null($options['hostids'])) {
			zbx_value2array($options['hostids']);
			$sqlParts['where']['autoreg_hostid'] = dbConditionInt('arh.autoreg_hostid', $options['hostids']);
		}
		
		// proxyids
		if (!is_null($options['proxyids'])) {
			zbx_value2array($options['proxyids']);

			$sqlParts['select']['proxy_hostid'] = 'arh.proxy_hostid';
			$sqlParts['where'][] = dbConditionInt('arh.proxy_hostid', $options['proxyids']);
		}
		
		// filter
		if (is_array($options['filter'])) {
			$this->dbFilter('autoreg_host arh', $options, $sqlParts);
		}
		
		$sqlParts = $this->applyQueryOutputOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$sqlParts = $this->applyQuerySortOptions($this->tableName(), $this->tableAlias(), $options, $sqlParts);
		$res = DBselect($this->createSelectQueryFromParts($sqlParts), $sqlParts['limit']);
		while ($host = DBfetch($res)) {
			if (!is_null($options['countOutput'])) {
				if (!is_null($options['groupCount'])) {
					$result[] = $host;
				}
				else {
					$result = $host['rowscount'];
				}
			}
			else {
				if (!isset($result[$host['autoreg_hostid']])) {
					$result[$host['autoreg_hostid']] = array();
				}

				$result[$host['autoreg_hostid']] += $host;
			}
		}

		if (!is_null($options['countOutput'])) {
			return $result;
		}

		// removing keys (hash -> array)
		if (is_null($options['preservekeys'])) {
			$result = zbx_cleanHashes($result);
		}

		return $result;
	}
	
	public function exists($object) {
		$keyFields = array(
			array(
				'autoreg_hostid',
				'host',
				'listen_ip',
				'listen_dns'
			)
		);

		$options = array(
			'filter' => zbx_array_mintersect($keyFields, $object),
			'output' => array('autoreg_hostid'),
			'limit' => 1
		);

		$objs = $this->get($options);

		return !empty($objs);
	}
}
