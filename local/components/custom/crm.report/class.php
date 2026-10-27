<?php

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true)
{
	die();
}

use Bitrix\Crm\ContactTable;
use Bitrix\Crm\DealTable;
use Bitrix\Crm\FieldMultiTable;
use Bitrix\Crm\StatusTable;
use Bitrix\Main\Grid\Options as GridOptions;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\ORM\Fields\Relations\Reference;
use Bitrix\Main\ORM\Query\Filter\ConditionTree;
use Bitrix\Main\ORM\Query\Join;
use Bitrix\Main\ORM\Query\Query;
use Bitrix\Main\Type\DateTime;
use Bitrix\Main\UI\Filter\Options as FilterOptions;
use Bitrix\Main\UI\PageNavigation;
use Bitrix\Main\UserTable;

class CustomCrmReportComponent extends CBitrixComponent
{
	private const PAGE_SIZES = [
		10,
		20,
		50
	];
	private const NAV_ID = 'report';

	private const GRID_ID = 'CUSTOM_CRM_REPORT';

	private const STAGE_ALIAS = 'REPORT_STAGE';
	private const USER_ALIAS = 'REPORT_DEAL_USER';
	private const CONTACT_ALIAS = 'REPORT_CONTACT';

	private const DEFAULT_SORT = 'DATE_CREATE';
	private const SORT_COLUMNS = [
		'ID' => ['ID'],
		'DATE_CREATE' => ['DATE_CREATE'],
		'ASSIGNED_BY' => [
			self::USER_ALIAS . '.LAST_NAME',
			self::USER_ALIAS . '.NAME'
		],
		'STAGE' => [self::STAGE_ALIAS . '.SORT'],
		'OPPORTUNITY' => ['OPPORTUNITY'],
	];

	public function onPrepareComponentParams($params)
	{
		$params['PAGE_SIZE'] = $this->normalizePageSize((int)($params['PAGE_SIZE'] ?? 0), self::PAGE_SIZES[0]);
		$params['DEAL_URL_TEMPLATE'] = trim((string)($params['DEAL_URL_TEMPLATE'] ?? ''))
			?: '/crm/deal/details/#ID#/';
		$params['USER_URL_TEMPLATE'] = trim((string)($params['USER_URL_TEMPLATE'] ?? ''))
			?: '/company/personal/user/#ID#/';
		$params['SET_TITLE'] = ($params['SET_TITLE'] ?? 'Y') === 'N' ? 'N' : 'Y';

		return $params;
	}

	public function executeComponent()
	{
		$error = $this->checkAccess();

		$this->arResult = $error === null ? $this->buildResult() : ['ERROR' => $error];

		if ($this->arParams['SET_TITLE'] === 'Y')
		{
			$GLOBALS['APPLICATION']->SetTitle((string)Loc::getMessage('CRM_REPORT_TITLE'));
		}

		$this->includeComponentTemplate();
	}

	private function checkAccess(): ?string
	{
		if (!Loader::includeModule('crm'))
		{
			return (string)Loc::getMessage('CRM_REPORT_MODULE_NOT_INSTALLED');
		}

		if (!CCrmDeal::CheckReadPermission())
		{
			return (string)Loc::getMessage('CRM_REPORT_ACCESS_DENIED');
		}

		return null;
	}

	private function buildResult(): array
	{
		$gridOptions = new GridOptions(self::GRID_ID);

		$filterFields = $this->getFilterFields($this->loadUsers(['=IS_REAL_USER' => 'Y']));
		$sort = $this->resolveSort($gridOptions);
		$pageSize = $this->resolvePageSize($gridOptions);

		$nav = new PageNavigation(self::NAV_ID);
		$nav->allowAllRecords(false)->setPageSize($pageSize)->initFromUri();

		$condition = $this->buildCondition($this->readFilter($filterFields));
		$page = $this->fetchDeals($condition, $sort, $nav->getLimit(), $nav->getOffset());
		$nav->setRecordCount($page['TOTAL']);

		return [
			'GRID_ID' => self::GRID_ID,
			'FILTER_FIELDS' => $filterFields,
			'COLUMNS' => $this->getColumns(),
			'ROWS' => $this->buildRows($page['ROWS']),
			'PAGE_SIZES' => array_map(
				static fn(int $size): array => [
					'NAME' => (string)$size,
					'VALUE' => (string)$size
				],
				self::PAGE_SIZES
			),
			'NAV' => $nav,
			'TOTAL' => $page['TOTAL'],
		];
	}

	private function getFilterFields(array $users): array
	{
		return [
			[
				'id' => 'ID',
				'name' => Loc::getMessage('CRM_REPORT_FILTER_ID'),
				'type' => 'number',
				'default' => true,
			],
			[
				'id' => 'DATE_CREATE',
				'name' => Loc::getMessage('CRM_REPORT_FILTER_DATE'),
				'type' => 'date',
				'default' => true,
			],
			[
				'id' => 'ASSIGNED_BY_ID',
				'name' => Loc::getMessage('CRM_REPORT_FILTER_DEAL_USER'),
				'type' => 'list',
				'items' => $users,
				'params' => ['multiple' => 'Y'],
				'default' => true,
			],
			[
				'id' => 'CONTACT_ASSIGNED_BY_ID',
				'name' => Loc::getMessage('CRM_REPORT_FILTER_CONTACT_USER'),
				'type' => 'list',
				'items' => $users,
				'params' => ['multiple' => 'Y'],
				'default' => true,
			],
		];
	}

	private function getColumns(): array
	{
		$contact = Loc::getMessage('CRM_REPORT_GROUP_CONTACT') . ': ';

		return [
			[
				'id' => 'ID',
				'name' => 'ID',
				'sort' => 'ID',
				'default' => true
			],
			[
				'id' => 'TITLE',
				'name' => Loc::getMessage('CRM_REPORT_COL_TITLE'),
				'default' => true
			],
			[
				'id' => 'DATE_CREATE',
				'name' => Loc::getMessage('CRM_REPORT_COL_DATE_CREATE'),
				'sort' => 'DATE_CREATE',
				'first_order' => 'desc',
				'default' => true,
			],
			[
				'id' => 'ASSIGNED_BY',
				'name' => Loc::getMessage('CRM_REPORT_COL_RESPONSIBLE'),
				'sort' => 'ASSIGNED_BY',
				'default' => true,
			],
			[
				'id' => 'STAGE',
				'name' => Loc::getMessage('CRM_REPORT_COL_STAGE'),
				'sort' => 'STAGE',
				'default' => true,
			],
			[
				'id' => 'OPPORTUNITY',
				'name' => Loc::getMessage('CRM_REPORT_COL_AMOUNT'),
				'sort' => 'OPPORTUNITY',
				'first_order' => 'desc',
				'align' => 'right',
				'default' => true,
			],
			[
				'id' => 'CONTACT_NAME',
				'name' => $contact . Loc::getMessage('CRM_REPORT_COL_CONTACT_NAME'),
				'default' => true,
			],
			[
				'id' => 'CONTACT_ASSIGNED_BY',
				'name' => $contact . Loc::getMessage('CRM_REPORT_COL_RESPONSIBLE'),
				'default' => true,
			],
			[
				'id' => 'CONTACT_PHONE',
				'name' => $contact . Loc::getMessage('CRM_REPORT_COL_PHONE'),
				'default' => true,
			],
			[
				'id' => 'CONTACT_EMAIL',
				'name' => $contact . Loc::getMessage('CRM_REPORT_COL_EMAIL'),
				'default' => true,
			],
		];
	}

	private function readFilter(array $fields): array
	{
		$dates = [];
		$data = (new FilterOptions(self::GRID_ID))->getFilter($fields);
		FilterOptions::calcDates('DATE_CREATE', $data, $dates);

		return [
			'ID_TYPE' => (string)($data['ID_numsel'] ?? ''),
			'ID_FROM' => trim((string)($data['ID_from'] ?? '')),
			'ID_TO' => trim((string)($data['ID_to'] ?? '')),
			'DATE_FROM' => DateTime::tryParse((string)($dates['DATE_CREATE_from'] ?? '')),
			'DATE_TO' => DateTime::tryParse((string)($dates['DATE_CREATE_to'] ?? '')),
			'DEAL_USERS' => $this->uniqueIds((array)($data['ASSIGNED_BY_ID'] ?? [])),
			'CONTACT_USERS' => $this->uniqueIds((array)($data['CONTACT_ASSIGNED_BY_ID'] ?? [])),
		];
	}

	private function resolveSort(GridOptions $gridOptions): array
	{
		$saved = $gridOptions->getSorting([
			'sort' => [self::DEFAULT_SORT => 'DESC'],
			'vars' => [
				'by' => 'by',
				'order' => 'order'
			],
		])['sort'] ?? [];

		$field = (string)array_key_first($saved);
		$direction = strtoupper((string)($saved[$field] ?? ''));

		return [
			'FIELD' => isset(self::SORT_COLUMNS[$field]) ? $field : self::DEFAULT_SORT,
			'DIRECTION' => $direction === 'ASC' ? 'ASC' : 'DESC',
		];
	}

	private function resolvePageSize(GridOptions $gridOptions): int
	{
		$default = $this->arParams['PAGE_SIZE'];
		$saved = (int)($gridOptions->getNavParams(['nPageSize' => $default])['nPageSize'] ?? 0);

		return $this->normalizePageSize($saved, $default);
	}

	private function normalizePageSize(int $requested, int $fallback): int
	{
		return in_array($requested, self::PAGE_SIZES, true) ? $requested : $fallback;
	}

	private function buildCondition(array $filter): ConditionTree
	{
		$condition = Query::filter();

		$this->applyIdFilter($condition, $filter);

		if ($filter['DATE_FROM'] !== null)
		{
			$condition->where('DATE_CREATE', '>=', $filter['DATE_FROM']);
		}

		if ($filter['DATE_TO'] !== null)
		{
			$condition->where('DATE_CREATE', '<=', $filter['DATE_TO']);
		}

		if ($filter['DEAL_USERS'] !== [])
		{
			$condition->whereIn('ASSIGNED_BY_ID', $filter['DEAL_USERS']);
		}

		if ($filter['CONTACT_USERS'] !== [])
		{
			$condition->whereIn(self::CONTACT_ALIAS . '.ASSIGNED_BY_ID', $filter['CONTACT_USERS']);
		}

		return $condition;
	}

	private function applyIdFilter(ConditionTree $condition, array $filter): void
	{
		$from = ctype_digit($filter['ID_FROM']) ? (int)$filter['ID_FROM'] : null;
		$to = ctype_digit($filter['ID_TO']) ? (int)$filter['ID_TO'] : null;

		switch ($filter['ID_TYPE'])
		{
			case 'exact':
				if ($from !== null)
				{
					$condition->where('ID', $from);
				}
				break;
			case 'more':
				if ($from !== null)
				{
					$condition->where('ID', '>', $from);
				}
				break;
			case 'less':
				if ($from !== null)
				{
					$condition->where('ID', '<', $from);
				}
				break;
			case 'range':
				if ($from !== null)
				{
					$condition->where('ID', '>=', $from);
				}
				if ($to !== null)
				{
					$condition->where('ID', '<=', $to);
				}
				break;
		}
	}

	private function fetchDeals(ConditionTree $condition, array $sort, int $limit, int $offset): array
	{
		$query = DealTable::query();

		foreach ($this->getReferences() as $reference)
		{
			$query->registerRuntimeField($reference->getName(), $reference);
		}

		$result = $query
			->setSelect([
				'ID',
				'TITLE',
				'DATE_CREATE',
				'ASSIGNED_BY_ID',
				'STAGE_ID',
				'OPPORTUNITY',
				'CURRENCY_ID',
				'CONTACT_ID',
				'STAGE_NAME' => self::STAGE_ALIAS . '.NAME',
				'CONTACT_NAME' => self::CONTACT_ALIAS . '.NAME',
				'CONTACT_LAST_NAME' => self::CONTACT_ALIAS . '.LAST_NAME',
				'CONTACT_SECOND_NAME' => self::CONTACT_ALIAS . '.SECOND_NAME',
				'CONTACT_ASSIGNED_BY_ID' => self::CONTACT_ALIAS . '.ASSIGNED_BY_ID',
			])
			->where($condition)
			->setOrder($this->buildOrder($sort))
			->setLimit($limit)
			->setOffset($offset)
			->countTotal(true)
			->exec()
		;

		return [
			'ROWS' => $result->fetchAll(),
			'TOTAL' => (int)$result->getCount(),
		];
	}

	private function getReferences(): array
	{
		return [
			new Reference(
				self::STAGE_ALIAS,
				StatusTable::class,
				Join::on('this.STAGE_ID', 'ref.STATUS_ID')->whereLike('ref.ENTITY_ID', 'DEAL_STAGE%')
			),
			new Reference(self::USER_ALIAS, UserTable::class, Join::on('this.ASSIGNED_BY_ID', 'ref.ID')),
			new Reference(self::CONTACT_ALIAS, ContactTable::class, Join::on('this.CONTACT_ID', 'ref.ID')),
		];
	}

	private function buildOrder(array $sort): array
	{
		$order = [];

		foreach (self::SORT_COLUMNS[$sort['FIELD']] as $column)
		{
			$order[$column] = $sort['DIRECTION'];
		}

		$order['ID'] = $sort['DIRECTION'];

		return $order;
	}

	private function loadUsers(array $filter): array
	{
		$names = [];
		$users = UserTable::getList([
			'select' => [
				'ID',
				'LOGIN',
				'NAME',
				'LAST_NAME',
				'SECOND_NAME'
			],
			'filter' => $filter,
			'order' => [
				'LAST_NAME' => 'ASC',
				'NAME' => 'ASC'
			],
		]);

		while ($user = $users->fetch())
		{
			$name = $this->joinName([
				$user['LAST_NAME'],
				$user['NAME'],
				$user['SECOND_NAME']
			]);
			$names[(int)$user['ID']] = $name !== '' ? $name : (string)$user['LOGIN'];
		}

		return $names;
	}

	private function loadChannels(array $contactIds): array
	{
		if ($contactIds === [])
		{
			return [];
		}

		$channels = [];
		$rows = FieldMultiTable::getList([
			'select' => [
				'ELEMENT_ID',
				'TYPE_ID',
				'VALUE'
			],
			'filter' => [
				'=ENTITY_ID' => 'CONTACT',
				'@ELEMENT_ID' => $contactIds,
				'@TYPE_ID' => [
					'PHONE',
					'EMAIL'
				],
			],
			'order' => ['ID' => 'ASC'],
		]);

		while ($row = $rows->fetch())
		{
			if ($row['VALUE'] !== '')
			{
				$channels[(int)$row['ELEMENT_ID']][$row['TYPE_ID']][] = $row['VALUE'];
			}
		}

		return $channels;
	}

	private function buildRows(array $rows): array
	{
		$userIds = $this->uniqueIds(array_merge(
			array_column($rows, 'ASSIGNED_BY_ID'),
			array_column($rows, 'CONTACT_ASSIGNED_BY_ID')
		));
		$userNames = $userIds === [] ? [] : $this->loadUsers(['@ID' => $userIds]);
		$channels = $this->loadChannels($this->uniqueIds(array_column($rows, 'CONTACT_ID')));

		$result = [];

		foreach ($rows as $row)
		{
			$contactId = (int)$row['CONTACT_ID'];
			$phones = $channels[$contactId]['PHONE'] ?? [];
			$emails = $channels[$contactId]['EMAIL'] ?? [];
			$amount = number_format((float)$row['OPPORTUNITY'], 2, '.', ' ') . ' ' . $row['CURRENCY_ID'];

			$result[] = [
				'id' => (int)$row['ID'],
				'columns' => [
					'ID' => (int)$row['ID'],
					'TITLE' => $this->link(
						str_replace('#ID#', (string)$row['ID'], $this->arParams['DEAL_URL_TEMPLATE']),
						(string)$row['TITLE']
					),
					'DATE_CREATE' => $row['DATE_CREATE'] instanceof DateTime
						? htmlspecialcharsbx($row['DATE_CREATE']->toString())
						: '',
					'ASSIGNED_BY' => $this->userLink($userNames, (int)$row['ASSIGNED_BY_ID']),
					'STAGE' => htmlspecialcharsbx((string)($row['STAGE_NAME'] ?: $row['STAGE_ID'])),
					'OPPORTUNITY' => htmlspecialcharsbx($amount),
					'CONTACT_NAME' => htmlspecialcharsbx($this->joinName([
						$row['CONTACT_LAST_NAME'],
						$row['CONTACT_NAME'],
						$row['CONTACT_SECOND_NAME'],
					])),
					'CONTACT_ASSIGNED_BY' => $contactId>0
						? $this->userLink($userNames, (int)$row['CONTACT_ASSIGNED_BY_ID'])
						: '',
					'CONTACT_PHONE' => implode('<br>', array_map('htmlspecialcharsbx', $phones)),
					'CONTACT_EMAIL' => implode('<br>', array_map(
						fn(string $email): string => $this->link('mailto:' . $email, $email, false),
						$emails
					)),
				],
			];
		}

		return $result;
	}

	private function link(string $url, string $title, bool $newTab = true): string
	{
		return sprintf(
			'<a href="%s"%s>%s</a>',
			htmlspecialcharsbx($url),
			$newTab ? ' target="_blank"' : '',
			htmlspecialcharsbx($title)
		);
	}

	private function userLink(array $userNames, int $userId): string
	{
		if (!isset($userNames[$userId]))
		{
			return '';
		}

		return $this->link(
			str_replace('#ID#', (string)$userId, $this->arParams['USER_URL_TEMPLATE']),
			$userNames[$userId]
		);
	}

	private function joinName(array $parts): string
	{
		return trim(implode(' ', array_filter($parts)));
	}

	private function uniqueIds(array $ids): array
	{
		return array_values(array_unique(array_filter(array_map('intval', $ids))));
	}
}
