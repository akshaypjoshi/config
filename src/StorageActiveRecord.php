<?php
/**
 * @link https://github.com/yii2tech
 * @copyright Copyright (c) 2015 Yii2tech
 * @license [New BSD License](http://www.opensource.org/licenses/bsd-license.php)
 */

namespace yii2tech\config;

/**
 * StorageActiveRecord is an configuration storage based on ActiveRecord.
 *
 * If you are using relational database, you can create table for such ActiveRecord using following migration code:
 *
 * ```php
 * $tableName = 'AppConfig';
 * $columns = [
 *     'id' => 'string',
 *     'value' => 'text',
 *     'PRIMARY KEY(id)',
 * ];
 * $this->createTable($tableName, $columns);
 * ```
 *
 * You may use same ActiveRecord class for multiple configuration storage providing [[filter]] value.
 *
 * @see \yii\db\ActiveRecordInterface
 *
 * @author Paul Klimov <klimov.paul@gmail.com>
 * @since 1.0
 */
class StorageActiveRecord extends Storage
{
    use StorageFilterTrait;

    /**
     * @var string name of the ActiveRecord class, which should be used for data finding and saving.
     * This class should match [[\yii\db\ActiveRecordInterface]] interface.
     */
    public $activeRecordClass;


    /**
     * {@inheritdoc}
     */
    public function save(array $values)
    {
        /* @var $activeRecordClass \yii\db\ActiveRecordInterface */
        /* @var $existingRecords \yii\db\ActiveRecordInterface[] */
        $activeRecordClass = $this->activeRecordClass;

        $filterAttributes = $this->composeFilterCondition();

        $existingRecords = $activeRecordClass::find()
            ->andWhere($filterAttributes)
            ->all();

        $result = true;

        foreach ($existingRecords as $key => $existingRecord) {
            if (array_key_exists($existingRecord->id, $values)) {
                $existingRecord->value = $values[$existingRecord->id];
                $result = $result && $existingRecord->save(false);
                unset($values[$existingRecord->id]);
                unset($existingRecords[$key]);
            }
        }

        foreach ($existingRecords as $existingRecord) {
            $existingRecord->delete();
        }

        foreach ($values as $id => $value) {
            /* @var $model \yii\db\ActiveRecordInterface */
            $model = new $activeRecordClass();
            $attributes = array_merge($filterAttributes, ['id' => $id, 'value' => $value]);
            foreach ($attributes as $attributeName => $attributeValue) {
                $model->$attributeName = $attributeValue;
            }
            $result = $result && $model->save(false);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function get()
    {
        /* @var $activeRecordClass \yii\db\ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;
        $rows = $activeRecordClass::find()
            ->andWhere($this->composeFilterCondition())
            ->all();

        $values = [];
        foreach ($rows as $row) {
            $values[$row->id] = $row->value;
        }

        return $values;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        /* @var $activeRecordClass \yii\db\ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;

        $result = true;
        foreach ($activeRecordClass::find()->andWhere($this->composeFilterCondition())->all() as $row) {
            /* @var $row \yii\db\ActiveRecordInterface */
            $result = $result && ($row->delete() > 0);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clearValue($id)
    {
        /* @var $activeRecordClass \yii\db\ActiveRecordInterface */
        /* @var $row \yii\db\ActiveRecordInterface */
        $activeRecordClass = $this->activeRecordClass;
        $row = $activeRecordClass::find()
            ->andWhere($this->composeFilterCondition(['id' => $id]))
            ->one();

        if ($row) {
            return $row->delete() > 0;
        }

        return true;
    }
}