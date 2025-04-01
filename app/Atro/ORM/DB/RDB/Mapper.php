<?php
/**
 * AtroCore Software
 *
 * This source file is available under GNU General Public License version 3 (GPLv3).
 * Full copyright and license information is available in LICENSE.txt, located in the root directory.
 *
 * @copyright  Copyright (c) AtroCore GmbH (https://www.atrocore.com)
 * @license    GPLv3 (https://www.gnu.org/licenses/)
 */

declare(strict_types=1);

namespace Atro\ORM\DB\RDB;

use Atro\Core\Utils\Config;
use Atro\ORM\DB\MapperInterface;
use Atro\ORM\DB\RDB\Query\QueryConverter;
use Atro\ORM\DB\RDB\QueryCallbacks\JoinManyToMany;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Atro\Core\Utils\Util;
use Espo\ORM\EntityFactory;
use Espo\ORM\IEntity;
use Atro\Core\Utils\Metadata;

class Mapper implements MapperInterface
{
    protected Connection $connection;
    protected EntityFactory $entityFactory;
    protected Metadata $metadata;
    protected QueryConverter $queryConverter;
    private array $singleParentHierarchy = [];

    public function __construct(Connection $connection, EntityFactory $entityFactory, Metadata $metadata)
    {
        $this->connection = $connection;
        $this->entityFactory = $entityFactory;
        $this->metadata = $metadata;
        $this->queryConverter = new QueryConverter($this->entityFactory, $this->connection);
    }

    public function selectById(IEntity $entity, string $id, $params = []): ?IEntity
    {
        $params['whereClause']['id'] = $id;

        $res = $this->select($entity, $params);
        if (empty($res[0])) {
            return null;
        }

        $row = $res[0];

        $this->addAttributeValues($entity, $row);

        $entity->set($row);
        $entity->setAsFetched();

        return $entity;
    }

    public function select(IEntity $entity, array $params): array
    {
        $qb = $this->createSelectQueryBuilder($entity, $params);

        try {
            $rows = $qb->fetchAllAssociative();
        } catch (\Throwable $e) {
            $sql = $qb->getSQL();
            $this->error("RDB SELECT failed for SQL: $sql");
            throw $e;
        }

        $result = [];
        foreach ($rows as $k => $row) {
            foreach ($row as $field => $value) {
                $result[$k][$this->getQueryConverter()->aliasToField($field)] = $value;
            }
        }

        return $result;
    }

    public function createSelectQueryBuilder(IEntity $entity, array $params, bool $innerSql = false): QueryBuilder
    {
        try {
            $queryData = $this->getQueryConverter()->createSelectQuery($entity->getEntityType(), $params, !empty($params['withDeleted']));
        } catch (\Throwable $e) {
            $this->error("RDB QUERY failed: {$e->getMessage()}");
            throw $e;
        }

        $qb = $this->connection->createQueryBuilder();

        foreach ($queryData['select'] ?? [] as $item) {
            $qb->addSelect($item);
        }

        if (!empty($queryData['distinct'])) {
            $qb->distinct();
        }

        $qb->from($this->connection->quoteIdentifier($queryData['table']['tableName']), $queryData['table']['tableAlias']);
        $qb->andWhere($queryData['where']);

        if (!empty($queryData['joins'])) {
            foreach ($queryData['joins'] as $v) {
                $qb->add('join', [
                    $v['fromAlias'] => [
                        'joinType'      => $v['type'],
                        'joinTable'     => $v['table'],
                        'joinAlias'     => $v['alias'],
                        'joinCondition' => $v['condition'],
                    ],
                ], true);
            }
        }

        foreach ($queryData['parameters'] ?? [] as $parameterName => $value) {
            $qb->setParameter($parameterName, $value, self::getParameterType($value));
        }

        if (isset($queryData['offset'])) {
            $qb->setFirstResult($params['offset']);
        }

        if (isset($queryData['limit'])) {
            $qb->setMaxResults($params['limit']);
        }

        if (!empty($queryData['order'])) {
            if (is_string($queryData['order'])) {
                $qb->add('orderBy', $queryData['order'], true);
            } elseif (is_array($queryData['order'])) {
                foreach ($queryData['order'] as $v) {
                    $qb->add('orderBy', $v, true);
                }
            }
        }

        if (!empty($queryData['groupBy'])) {
            $qb->groupBy($queryData['groupBy']);
        }

        // select parent_id if single parent hierarchy
        if ($this->isSingleParentHierarchy($entity) && empty($params['aggregation']) && empty($params['disableParentLoad']) && !$innerSql) {
            $tableName = $this->getQueryConverter()->toDb($entity->getEntityType());
            $ta = $this->getQueryConverter()::TABLE_ALIAS;
            $relAlias1 = 'hierarchy_alias_' . Util::generateUniqueHash();
            $relAlias2 = 'alias_' . Util::generateUniqueHash();

            $qb->addSelect("$relAlias1.parent_id AS " . $this->getQueryConverter()->fieldToAlias('parentId'));
            $qb->addSelect("$relAlias2.name AS " . $this->getQueryConverter()->fieldToAlias('parentName'));
            $qb->leftJoin($ta, "{$tableName}_hierarchy", $relAlias1, "$ta.id=$relAlias1.entity_id AND $relAlias1.deleted=:false");
            $qb->leftJoin($relAlias1, $tableName, $relAlias2, "$relAlias2.id=$relAlias1.parent_id AND $relAlias1.deleted=:false");
            $qb->setParameter('false', false, ParameterType::BOOLEAN);
        }

        if (!empty($params['callbacks']) && !$innerSql) {
            foreach ($params['callbacks'] as $callback) {
                call_user_func($callback, $qb, $entity, $params, $this);
            }
        }

        return $qb;
    }

    protected function isSingleParentHierarchy(IEntity $entity): bool
    {
        if (!isset($this->singleParentHierarchy[$entity->getEntityType()])) {
            $scopeDefs = $this->metadata->get(['scopes', $entity->getEntityType()], []);
            $this->singleParentHierarchy[$entity->getEntityType()] = !empty($scopeDefs['type']) && $scopeDefs['type'] === 'Hierarchy' && empty($scopeDefs['disableHierarchy']) && empty($scopeDefs['multiParents']);
        }

        return $this->singleParentHierarchy[$entity->getEntityType()];
    }

    public function count(IEntity $entity, array $params = []): int
    {
        $params['aggregation'] = 'COUNT';
        $params['aggregationBy'] = 'id';
        $params['skipBelongsToJoins'] = true;

        $res = $this->select($entity, $params);
        foreach ($res as $row) {
            $count = $row[QueryConverter::AGGREGATE_VALUE] ?? 0;
            return (int)$count;
        }

        return 0;
    }

    public function selectRelated(IEntity $entity, string $relationName, array $params = [], bool $totalCount = false)
    {
        $relOpt = $entity->relations[$relationName];

        if (!isset($relOpt['type'])) {
            throw new \LogicException("Missing 'type' in definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
        }

        if ($relOpt['type'] !== IEntity::BELONGS_TO_PARENT) {
            if (!isset($relOpt['entity'])) {
                throw new \LogicException("Missing 'entity' in definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
            }

            $relEntityName = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];
            $relEntity = $this->entityFactory->create($relEntityName);

            if (!$relEntity) {
                return null;
            }
        }

        if ($totalCount) {
            $params['aggregation'] = 'COUNT';
            $params['aggregationBy'] = 'id';
        }

        if (empty($params['whereClause'])) {
            $params['whereClause'] = [];
        }

        $relType = $relOpt['type'];

        $keySet = $this->getKeys($entity, $relationName);

        $key = $keySet['key'];
        $foreignKey = $keySet['foreignKey'];

        switch ($relType) {
            case IEntity::BELONGS_TO:
                $params['whereClause'][$foreignKey] = $entity->get($key);
                $params['offset'] = 0;
                $params['limit'] = 1;

                $rows = $this->select($relEntity, $params);

                if ($rows) {
                    foreach ($rows as $row) {
                        if (!$totalCount) {
                            $relEntity->set($row);
                            $relEntity->setAsFetched();
                            return $relEntity;
                        } else {
                            return $row[QueryConverter::AGGREGATE_VALUE];
                        }
                    }
                }
                return null;
            case IEntity::HAS_MANY:
            case IEntity::HAS_CHILDREN:
            case IEntity::HAS_ONE:
                $params['whereClause'][$foreignKey] = $entity->get($key);

                if ($relType == IEntity::HAS_CHILDREN) {
                    $foreignType = $keySet['foreignType'];
                    $params['whereClause'][$foreignType] = $entity->getEntityType();
                }

                if ($relType == IEntity::HAS_ONE) {
                    $params['offset'] = 0;
                    $params['limit'] = 1;
                }

                $resultArr = [];

                $rows = $this->select($relEntity, $params);
                if ($rows) {
                    if (!$totalCount) {
                        $resultArr = $rows;
                    } else {
                        foreach ($rows as $row) {
                            return $row[QueryConverter::AGGREGATE_VALUE];
                        }
                    }
                }

                if ($relType == IEntity::HAS_ONE) {
                    if (count($resultArr)) {
                        $relEntity->set($resultArr[0]);
                        $relEntity->setAsFetched();
                        return $relEntity;
                    }
                    return null;
                } else {
                    return $resultArr;
                }

            case IEntity::MANY_MANY:
                $params['relationName'] = $relOpt['relationName'];
                $params['callbacks'][] = [new JoinManyToMany($entity, $relationName, $keySet), 'run'];

                $resultArr = [];
                $rows = $this->select($relEntity, $params);
                if ($rows) {
                    if (!$totalCount) {
                        $resultArr = $rows;
                    } else {
                        foreach ($rows as $row) {
                            return $row[QueryConverter::AGGREGATE_VALUE];
                        }
                    }
                }
                return $resultArr;
            case IEntity::BELONGS_TO_PARENT:
                $foreignEntityType = $entity->get($keySet['typeKey']);
                $foreignEntityId = $entity->get($key);
                if (!$foreignEntityType || !$foreignEntityId) {
                    return null;
                }
                $params['whereClause'][$foreignKey] = $foreignEntityId;
                $params['offset'] = 0;
                $params['limit'] = 1;

                $relEntity = $this->entityFactory->create($foreignEntityType);

                $rows = $this->select($relEntity, $params);

                if ($rows) {
                    foreach ($rows as $row) {
                        if (!$totalCount) {
                            $relEntity->set($row);
                            return $relEntity;
                        } else {
                            return $row[QueryConverter::AGGREGATE_VALUE];
                        }
                    }
                }
                return null;
        }

        return null;
    }

    public function countRelated(IEntity $entity, string $relName, array $params): int
    {
        $res = $this->selectRelated($entity, $relName, $params, true);
        return (int)$res;
    }

    /**
     * @deprecated
     */
    public function addRelation(IEntity $entity, string $relName, string $id = null, IEntity $relEntity = null, array $data = null): bool
    {
        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) || empty($relName)) {
            return false;
        }

        $relOpt = $entity->relations[$relName];

        if (!isset($relOpt['entity']) || !isset($relOpt['type'])) {
            return false;
        }

        $relType = $relOpt['type'];

        $className = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($className);
            if (!$relEntity) {
                return false;
            }
            $relEntity->id = $id;
        }

        $keySet = $this->getKeys($entity, $relName);

        if ($relType !== IEntity::MANY_MANY) {
            return false;
        }

        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $relTable = $this->toDb($relOpt['relationName']);

        $qb = $this->connection->createQueryBuilder();
        $qb->insert($relTable);

        $qb->setValue('id', ":id")
            ->setParameter('id', Util::generateId());

        $qb->setValue($this->toDb($nearKey), ":$nearKey")
            ->setParameter($nearKey, $entity->id);

        $qb->setValue($this->toDb($distantKey), ":$distantKey")
            ->setParameter($distantKey, $relEntity->id);

        $qb->setValue('created_at', ":currentDate")
            ->setValue('modified_at', ":currentDate")
            ->setParameter('currentDate', date('Y-m-d H:i:s'));

        $qb->setValue('created_by_id', ":userId")
            ->setValue('modified_by_id', ":userId")
            ->setParameter('userId', $this->entityFactory->getEntityManager()->getUser()->get('id'));

        if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
            foreach ($relOpt['conditions'] as $f => $v) {
                $qb->setValue($this->toDb($f), ":$f")->setParameter($f, $v);
            }
        }
        if (!empty($data) && is_array($data)) {
            foreach ($data as $column => $columnValue) {
                $qb->setValue($this->toDb($column), ":$column")->setParameter($column, $columnValue);
            }
        }

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
            $sql = $qb->getSQL();
            $this->error("RDB addRelation failed for SQL: $sql");
            throw $e;
        }

        return true;
    }

    /**
     * @deprecated
     */
    public function removeRelation(IEntity $entity, string $relationName, string $id = null, bool $all = false, IEntity $relEntity = null): bool
    {
        if (!is_null($relEntity)) {
            $id = $relEntity->id;
        }

        if (empty($id) && empty($all) || empty($relationName)) {
            return false;
        }

        $relOpt = $entity->relations[$relationName];

        if (!isset($relOpt['entity']) || !isset($relOpt['type'])) {
            throw new \LogicException("Not appropriate definition for relationship {$relationName} in " . $entity->getEntityType() . " entity");
        }

        $relType = $relOpt['type'];

        $className = (!empty($relOpt['class'])) ? $relOpt['class'] : $relOpt['entity'];

        if (is_null($relEntity)) {
            $relEntity = $this->entityFactory->create($className);
            if (!$relEntity) {
                return false;
            }
            $relEntity->id = $id;
        }

        $keySet = $this->getKeys($entity, $relationName);

        if ($relType !== IEntity::MANY_MANY) {
            return false;
        }

        $nearKey = $keySet['nearKey'];
        $distantKey = $keySet['distantKey'];

        $relTable = $this->toDb($relOpt['relationName']);

        $qb = $this->connection->createQueryBuilder();
        $qb->where("{$this->toDb($nearKey)} = :$nearKey");
        $qb->setParameter($nearKey, $entity->id, self::getParameterType($entity->id));

        if (empty($all)) {
            $qb->andWhere("{$this->toDb($distantKey)} = :$distantKey");
            $qb->setParameter($distantKey, $id, self::getParameterType($id));
        }

        if (!empty($relOpt['conditions']) && is_array($relOpt['conditions'])) {
            foreach ($relOpt['conditions'] as $f => $v) {
                $qb->andWhere("{$this->toDb($f)} = :$f");
                $qb->setParameter($f, $v, self::getParameterType($v));
            }
        }

        // delete prev
        $qb1 = clone $qb;
        $qb1->delete($this->connection->quoteIdentifier($relTable))
            ->andWhere('deleted = :true')
            ->setParameter('true', true, ParameterType::BOOLEAN);
        try {
            $qb1->executeQuery();
        } catch (\Throwable $e) {
            $sql = $qb1->getSQL();
            $this->error("RDB removeRelation failed for SQL: $sql");
            throw $e;
        }

        // update current
        $qb2 = clone $qb;
        $qb2
            ->update($this->connection->quoteIdentifier($relTable))
            ->set('deleted', ':true')
            ->set('modified_at', ':currentDate')
            ->set('modified_by_id', ':userId')
            ->setParameter('true', true, ParameterType::BOOLEAN)
            ->setParameter('currentDate', date('Y-m-d H:i:s'))
            ->setParameter('userId', $this->entityFactory->getEntityManager()->getUser()->get('id'));

        try {
            $qb2->executeQuery();
        } catch (\Throwable $e) {
            $sql = $qb2->getSQL();
            $this->error("RDB removeRelation failed for SQL: $sql");
            throw $e;
        }

        return true;
    }

    public function insert(IEntity $entity, bool $ignoreDuplicate = false): bool
    {
        $dataArr = $this->toValueMap($entity);

        if (!empty($dataArr)) {
            $qb = $this->connection->createQueryBuilder();

            $qb->insert($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())));
            foreach ($dataArr as $field => $value) {
                $value = $this->prepareValueForUpdate($entity->getAttributeType($field), $value);
                $qb->setValue($this->connection->quoteIdentifier($this->toDb($field)), ":i_$field");
                $qb->setParameter("i_$field", $value, self::getParameterType($value));
            }

            $sql = $qb->getSQL();

            if ($ignoreDuplicate) {
                if ($this->connection->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                    $sql .= ' ON CONFLICT DO NOTHING';
                } else {
                    $sql = str_replace('INSERT INTO', 'INSERT IGNORE INTO', $sql);
                }
            }

            try {
                $this->connection->executeQuery($sql, $qb->getParameters(), $qb->getParameterTypes());
            } catch (\Throwable $e) {
                $sql = $qb->getSQL();
                $this->error("RDB INSERT failed for SQL: $sql. Message: {$e->getMessage()}");
                throw $e;
            }
        }

        return true;
    }

    public function update(IEntity $entity): bool
    {
        $setArr = [];
        $attrs = [];
        foreach ($this->toValueMap($entity) as $attribute => $value) {
            if ($attribute == 'id') {
                continue;
            }
            $type = $entity->getAttributeType($attribute);

            if ($type == IEntity::FOREIGN) {
                continue;
            }

            if (!$entity->isAttributeChanged($attribute) && $type !== IEntity::JSON_OBJECT) {
                continue;
            }

            if (!empty($entity->fields[$attribute]['column'])) {
                $attrs[$attribute] = $value;
            } else {
                $setArr[$attribute] = $this->prepareValueForUpdate($type, $value);
            }
        }

        if (count($setArr) == 0) {
            return true;
        }

        $qb = $this->connection->createQueryBuilder();

        $qb->update($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())));
        foreach ($setArr as $field => $value) {
            $qb->set($this->connection->quoteIdentifier($this->toDb($field)), ":u_$field");
            $qb->setParameter("u_$field", $value, self::getParameterType($value));
        }

        $qb->where('id = :id');
        $qb->setParameter('id', $entity->id, self::getParameterType($entity->id));
        $qb->andWhere('deleted = :deleted');
        $qb->setParameter('deleted', false, self::getParameterType(false));

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
            $sql = $qb->getSQL();
            $this->error("RDB UPDATE failed for SQL: $sql");
            throw $e;
        }

        if ($this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            $name = $this->toDb(lcfirst($entity->getEntityType()));
            foreach ($attrs as $key => $value) {
                if ($value !== null && $entity->fields[$key]['type'] === 'jsonArray') {
                    $value = json_encode($value);
                }
                $this->connection->createQueryBuilder()
                    ->update("{$name}_attribute_value")
                    ->set($entity->fields[$key]['column'], ':value')
                    ->where('id=:id')
                    ->setParameter('value', $value, self::getParameterType($value))
                    ->setParameter('id', $entity->fields[$key]['attributeValueId'])
                    ->executeQuery();
            }
        }

        return true;
    }

    public function delete(IEntity $entity): bool
    {
        $qb = $this->connection->createQueryBuilder();

        $qb->delete($this->connection->quoteIdentifier($this->toDb($entity->getEntityType())))
            ->where('id = :id')
            ->setParameter('id', $entity->id, self::getParameterType($entity->id));

        try {
            $qb->executeQuery();
        } catch (\Throwable $e) {
            $sql = $qb->getSQL();
            $this->error("RDB DELETE failed for SQL: $sql");
            throw $e;
        }

        return true;
    }

    public static function getParameterType($value): ?int
    {
        if (is_bool($value)) {
            return ParameterType::BOOLEAN;
        }

        if (is_array($value)) {
            $res = Connection::PARAM_INT_ARRAY;
            if (!empty($value[0]) && is_string($value[0])) {
                $res = Connection::PARAM_STR_ARRAY;
            }
            return $res;
        }

        if (is_string($value)) {
            return ParameterType::STRING;
        }

        if ($value === null) {
            return ParameterType::NULL;
        }

        return null;
    }

    public function toValueMap(IEntity $entity, bool $onlyStorable = true): array
    {
        $data = [];
        foreach ($entity->getAttributes() as $attribute => $defs) {
            if ($entity->has($attribute)) {
                if ($onlyStorable) {
                    if (
                        !empty($defs['notStorable'])
                        || !empty($defs['autoincrement'])
                        || isset($defs['source']) && $defs['source'] != 'db'
                    ) {
                        continue;
                    }
                    if ($defs['type'] == IEntity::FOREIGN) {
                        continue;
                    }
                }
                $data[$attribute] = $entity->get($attribute);
            }
        }

        return $data;
    }

    public function prepareValueForUpdate($type, $value)
    {
        if ($type == IEntity::JSON_ARRAY && is_array($value)) {
            $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
        } else {
            if ($type == IEntity::JSON_OBJECT && (is_array($value) || $value instanceof \stdClass)) {
                $value = json_encode($value, \JSON_UNESCAPED_UNICODE);
            }
        }

        return $value;
    }

    protected function addAttributeValues(IEntity $entity, array &$data): void
    {
        if (!$this->metadata->get("scopes.{$entity->getEntityType()}.hasAttribute")) {
            return;
        }

        $languages = [];
        if (!empty($this->getConfig()->get('isMultilangActive'))) {
            foreach ($this->getConfig()->get('inputLanguageList', []) as $code) {
                $languages[$code] = $code;
                foreach ($this->getConfig()->get('referenceData.Language', []) as $v) {
                    if ($code === $v['code']) {
                        $languages[$code] = $v['name'];
                        break;
                    }
                }
            }
        }

        $tableName = $this->toDb(lcfirst($entity->getEntityType()));
        $res = $this->connection->createQueryBuilder()
            ->select('a.*, av.id as av_id, av.bool_value, av.date_value, av.datetime_value, av.int_value, av.int_value1, av.float_value, av.float_value1, av.varchar_value, av.text_value, av.reference_value, av.json_value, f.name as file_name')
            ->from("{$tableName}_attribute_value", 'av')
            ->leftJoin('av', $this->connection->quoteIdentifier('attribute'), 'a', 'a.id=av.attribute_id')
            ->leftJoin('av', $this->connection->quoteIdentifier('file'), 'f', 'f.id=av.reference_value AND a.type=:fileType')
            ->where('av.deleted=:false')
            ->andWhere('a.deleted=:false')
            ->andWhere("av.{$tableName}_id=:id")
            ->orderBy('a.sort_order', 'ASC')
            ->setParameter('false', false, ParameterType::BOOLEAN)
            ->setParameter('id', $data['id'])
            ->setParameter('fileType', 'file')
            ->fetchAllAssociative();

        $data['attributeValues'] = [];

        foreach ($res as $row) {
            $id = $row['av_id'];
            $name = "attr_{$id}";

            $attributeRow = [
                'id'                            => $id,
                'attributeId'                   => $row['id'],
                'name'                          => $name,
                'label'                         => $row['name'],
                'type'                          => $row['type'],
                'trim'                          => !empty($row['trim']),
                'required'                      => !empty($row['is_required']),
                'notNull'                       => !empty($row['not_null']),
                'useDisabledTextareaInViewMode' => !empty($row['use_disabled_textarea_in_view_mode']),
                'amountOfDigitsAfterComma'      => $row['amount_of_digits_after_comma'] ?? null,
                'prohibitedEmptyValue'          => !empty($row['prohibited_empty_value']),
                'extensibleEnumId'              => $row['extensible_enum_id'] ?? null
            ];

            $attributeData = @json_decode($row['data'], true)['field'] ?? null;

            if (!empty($attributeData['entityType'])){
                $attributeRow['entity'] = $attributeData['entityType'];
            }

            if (!empty($attributeData['maxLength'])) {
                $attributeRow['maxLength'] = $attributeData['maxLength'];
            }

            if (!empty($attributeData['countBytesInsteadOfCharacters'])) {
                $attributeRow['countBytesInsteadOfCharacters'] = $attributeData['countBytesInsteadOfCharacters'];
            }

            if (isset($attributeData['min'])) {
                $attributeRow['min'] = $attributeData['min'];
            }

            if (isset($attributeData['max'])) {
                $attributeRow['max'] = $attributeData['max'];
            }

            if (isset($row['measure_id'])) {
                $attributeRow['measureId'] = $row['measure_id'];
                $attributeRow['view'] = "views/fields/unit-{$row['type']}";
            }

            $dropdownTypes = $this->getMetadata()->get(['app', 'attributeDropdownTypes'], []);
            if (!empty($row['dropdown']) && isset($dropdownTypes[$item['type']])) {
                $attributeRow['view'] = $dropdownTypes[$row['type']];
            }

            $data['attributeValues'][] = $attributeRow;
            if (!empty($row['is_multilang'])) {
                foreach ($languages as $language => $languageName) {
                    $data['attributeValues'][] = array_merge($attributeRow, [
                        'name'  => $row['id'] . ucfirst(Util::toCamelCase(strtolower($language))),
                        'label' => $row['name'] . ' / ' . $languageName
                    ]);
                }
            }

            switch ($row['type']) {
                case 'extensibleEnum':
                    $entity->fields[$name] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "reference_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;
                    break;
                case 'extensibleMultiEnum':
                case 'array':
                    $entity->fields[$name] = [
                        'type'             => 'jsonArray',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "json_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;
                    break;
                case 'bool':
                    $entity->fields[$name] = [
                        'type'             => 'bool',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "bool_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;
                    break;
                case 'int':
                    $entity->fields[$name] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "int_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'UnitId'] = $row[$entity->fields[$name . 'UnitId']['column']] ?? null;
                    break;
                case 'rangeInt':
                    $entity->fields[$name . 'From'] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'int_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'From'] = $row[$entity->fields[$name . 'From']['column']] ?? null;

                    $entity->fields[$name . 'To'] = [
                        'type'             => 'int',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'int_value1',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'To'] = $row[$entity->fields[$name . 'To']['column']] ?? null;

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'UnitId'] = $row[$entity->fields[$name . 'UnitId']['column']] ?? null;
                    break;
                case 'float':
                    $entity->fields[$name] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "float_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'UnitId'] = $row[$entity->fields[$name . 'UnitId']['column']] ?? null;
                    break;
                case 'rangeFloat':
                    $entity->fields[$name . 'From'] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'float_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'From'] = $row[$entity->fields[$name . 'From']['column']] ?? null;

                    $entity->fields[$name . 'To'] = [
                        'type'             => 'float',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'float_value1',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'To'] = $row[$entity->fields[$name . 'To']['column']] ?? null;

                    $entity->fields[$name . 'UnitId'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'UnitId'] = $row[$entity->fields[$name . 'UnitId']['column']] ?? null;
                    break;
                case 'date':
                    $entity->fields[$name] = [
                        'type'             => 'date',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "date_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;
                    break;
                case 'datetime':
                    $entity->fields[$name] = [
                        'type'             => 'datetime',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "datetime_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;
                    break;
                case 'file':
                    $entity->fields[$name . 'Id'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'Id'] = $row[$entity->fields[$name . 'Id']['column']] ?? null;

                    $entity->fields[$name . 'Name'] = [
                        'type'        => 'varchar',
                        'notStorable' => true
                    ];
                    $entity->fields[$name . 'PathsData'] = [
                        'type'        => 'jsonObject',
                        'notStorable' => true
                    ];
                    $data[$name . 'Name'] = $row['file_name'] ?? null;
                    break;
                case 'link':
                    $entity->fields[$name . 'Id'] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => 'reference_value',
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name . 'Id'] = $row[$entity->fields[$name . 'Id']['column']] ?? null;

                    if (!empty($attributeData['entityType'])) {
                        $referenceTable = Util::toUnderScore(lcfirst($attributeData['entityType']));
                        try {
                            $referenceItem = $this->connection->createQueryBuilder()
                                ->select('id, name')
                                ->from($referenceTable)
                                ->where('id=:id')
                                ->andWhere('deleted=:false')
                                ->setParameter('id', $row['reference_value'])
                                ->setParameter('false', false, ParameterType::BOOLEAN)
                                ->fetchAssociative();

                            $entity->fields[$name . 'Name'] = [
                                'type'        => 'varchar',
                                'notStorable' => true
                            ];
                            $data[$name . 'Name'] = $referenceItem['name'] ?? null;
                        } catch (\Throwable $e) {
                            // ignore all
                        }
                    }
                    break;
                case 'text':
                case 'markdown':
                case 'wysiwyg':
                    $entity->fields[$name] = [
                        'type'             => 'text',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "text_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;

                    if (!empty($row['is_multilang'])) {
                        foreach ($languages as $language => $languageName) {
                            $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                            $entity->fields[$lName] = [
                                'type'             => 'text',
                                'name'             => $name,
                                'attributeValueId' => $id,
                                'attributeId'      => $row['id'],
                                'attributeName'    => $row['name'] . ' / ' . $languageName,
                                'attributeType'    => $row['type'],
                                'column'           => "text_value_" . strtolower($language),
                                'required'         => !empty($row['is_required'])
                            ];
                            $data[$lName] = $row[$entity->fields[$lName]['column']] ?? null;
                        }
                    }
                    break;
                case 'varchar':
                    $entity->fields[$name] = [
                        'type'             => 'varchar',
                        'name'             => $name,
                        'attributeValueId' => $id,
                        'attributeId'      => $row['id'],
                        'attributeName'    => $row['name'],
                        'attributeType'    => $row['type'],
                        'column'           => "varchar_value",
                        'required'         => !empty($row['is_required'])
                    ];
                    $data[$name] = $row[$entity->fields[$name]['column']] ?? null;

                    if (!empty($row['is_multilang'])) {
                        foreach ($languages as $language => $languageName) {
                            $lName = $name . ucfirst(Util::toCamelCase(strtolower($language)));
                            $entity->fields[$lName] = [
                                'type'             => 'varchar',
                                'name'             => $name,
                                'attributeValueId' => $id,
                                'attributeId'      => $row['id'],
                                'attributeName'    => $row['name'] . ' / ' . $languageName,
                                'attributeType'    => $row['type'],
                                'column'           => "varchar_value_" . strtolower($language),
                                'required'         => !empty($row['is_required'])
                            ];
                            $data[$lName] = $row[$entity->fields[$lName]['column']] ?? null;
                        }
                    }
                    break;
            }
        }
    }

    public function toDb(string $field): string
    {
        return $this->getQueryConverter()->toDb($field);
    }

    public function getKeys(IEntity $entity, string $relationName): array
    {
        return $this->getQueryConverter()->getKeys($entity, $relationName);
    }

    public function getQueryConverter(): QueryConverter
    {
        return $this->queryConverter;
    }

    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    public function getEntityFactory(): EntityFactory
    {
        return $this->entityFactory;
    }

    protected function getConfig(): Config
    {
        return $this->metadata->getConfig();
    }

    private function error(string $message): void
    {
        if (!empty($GLOBALS['debug'])) {
            $GLOBALS['log']->error($message);
        }
    }
}
