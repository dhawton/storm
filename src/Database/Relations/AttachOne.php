<?php namespace Winter\Storm\Database\Relations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphOne as MorphOneBase;
use Winter\Storm\Database\Attach\File as FileModel;

class AttachOne extends MorphOneBase
{
    use AttachOneOrMany;
    use DefinedConstraints;

    /**
     * Create a new has many relationship instance.
     * @param Builder $query
     * @param Model $parent
     * @param $type
     * @param $id
     * @param $isPublic
     * @param $localKey
     * @param null|string $relationName
     * @param null|string $keyType
     */
    public function __construct(Builder $query, Model $parent, $type, $id, $isPublic, $localKey, $relationName = null, $keyType = null)
    {
        $this->relationName = $relationName;

        $this->public = $isPublic;

        /**
         *  Since Laravel 5.7, whereInMethod has been added
         *  https://github.com/illuminate/database/blob/5.7/Eloquent/Relations/Relation.php#L317-L324
         *  Because of that, the attachOne relationships, which relies on a string key 'attachment_id' must be set to the right type
         *  Without that, it would call the whereIn method and fails on eagerLoad on some strict DBMS (occurred on PostgreSQL)
         *  It has been set to string as default into the \Database\Attach\File class but could be overridden by the user
         */
        if ($keyType !== null) {
            $parent->setKeyType($keyType);
        }

        parent::__construct($query, $parent, $type, $id, $localKey);

        $this->addDefinedConstraints();
    }

    /**
     * Helper for setting this relationship using various expected
     * values. For example, $model->relation = $value;
     */
    public function setSimpleValue($value)
    {
        if (is_array($value)) {
            $value = reset($value);
        }

        /*
         * Newly uploaded file
         */
        if ($this->isValidFileData($value)) {
            $this->parent->bindEventOnce('model.afterSave', function () use ($value) {
                $file = $this->create(['data' => $value]);
                $this->parent->setRelation($this->relationName, $file);
            });
        }
        /*
         * Existing File model
         */
        elseif ($value instanceof FileModel) {
            $this->parent->bindEventOnce('model.afterSave', function () use ($value) {
                $this->add($value);
            });
        }

        /*
         * The relation is set here to satisfy `getValidationValue`
         */
        $this->parent->setRelation($this->relationName, $value);
    }

    /**
     * Helper for getting this relationship simple value,
     * generally useful with form values.
     */
    public function getSimpleValue()
    {
        if ($value = $this->getSimpleValueInternal()) {
            return $value->getPath();
        }

        return null;
    }

    /**
     * Helper for getting this relationship validation value.
     */
    public function getValidationValue()
    {
        if ($value = $this->getSimpleValueInternal()) {
            return $this->makeValidationFile($value);
        }

        return null;
    }

    /**
     * Internal method used by `getSimpleValue` and `getValidationValue`
     */
    protected function getSimpleValueInternal()
    {
        $value = null;

        $file = ($sessionKey = $this->parent->sessionKey)
            ? $this->withDeferred($sessionKey)->first()
            : $this->parent->{$this->relationName};

        if ($file) {
            $value = $file;
        }

        return $value;
    }
}
