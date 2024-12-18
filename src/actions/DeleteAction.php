<?php

/**
 * @copyright Copyright (c) 2020 Insolita <webmaster100500@ya.ru> and contributors
 * @license https://github.com/insolita/yii2-fractal/blob/master/LICENSE
 */

namespace insolita\fractal\actions;

use Closure;
use Yii;
use yii\base\Model;
use yii\base\InvalidConfigException;
use yii\web\ForbiddenHttpException;
use yii\web\ServerErrorHttpException;

/**
 * Handler for routes DELETE /resource/{id}
 *  With defined parentIdParam and parentIdAttribute Handler for  DELETE /resource/{parentId}/relation/{id} modelClass
 * should be defined for related model for this case
 **/
class DeleteAction extends JsonApiAction
{
    use HasParentAttributes;

    /**
     * @var string|callable
     * string - the scenario to be assigned to the model before it is validated and saved.
     * callable - a PHP callable that will be executed during the action.
     * It must return a string representing the scenario to be assigned to the model before it is validated and saved.
     *  The signature of the callable should be as follows,
     *  ```php
     *  function ($action, $model) {
     *      // $model is the requested model instance.
     *  }
     *  ```
     */
    public $scenario = Model::SCENARIO_DEFAULT;

    /**
     * @var callable|null A PHP callable that will be called to determine
     * whether the deletion of a model is allowed. If not set, no deletion
     * check will be performed. The callable should have the following signature:
     *
     * @example
     * ```php
     * function ($action, $model) {
     *     // $model is the model instance being deleted.
     *
     *     // If the deletion is not allowed, an error should be thrown. For example:
     *     if ($model->status !== 'draft') {
     *         throw new MethodNotAllowedHttpException('The model can only be deleted if its status is "draft".');
     *     }
     * }
     * ```
     */
    public $checkDeleteAllowed;


    /**
     * @var callable|Closure Callback after save model with all relations
     * @example
     *   'afterDelete' => function ($model) {
     *           doSomething();
     * }
     */
    public $afterDelete = null;

    public function init():void
    {
        parent::init();
        $this->validateParentAttributes();
    }

    /**
     * @param int|string $id
     * @throws \Throwable
     * @throws \yii\db\StaleObjectException
     * @throws \yii\web\NotFoundHttpException
     * @throws \yii\web\ServerErrorHttpException
     */
    public function run($id):void
    {
        if ($this->hasResourceRelationships()) {
            /** @see https://jsonapi.org/format/#crud-updating-resource-relationships * */
            throw new ForbiddenHttpException('Update with relationships not supported yet');
        }
        $model = $this->isParentRestrictionRequired() ? $this->findModelForParent($id) : $this->findModel($id);

        if (is_string($this->scenario)) {
            $scenario = $this->scenario;
        } elseif (is_callable($this->scenario)) {
            $scenario = call_user_func($this->scenario, $this->id, $model);
        } else {
            throw new InvalidConfigException('The "scenario" property must be defined either as a string or as a callable.');
        }
        $model->setScenario($scenario);

        if ($this->checkAccess) {
            call_user_func($this->checkAccess, $this->id, $model);
        }

        if ($this->checkDeleteAllowed) {
            call_user_func($this->checkDeleteAllowed, $this->id, $model);
        }

        if ($model->delete() === false) {
            throw new ServerErrorHttpException('Failed to delete the object for unknown reason.');
        }
        if ($this->afterDelete !== null) {
            call_user_func($this->afterDelete, $model);
        }
        Yii::$app->getResponse()->setStatusCode(204);
    }
}
