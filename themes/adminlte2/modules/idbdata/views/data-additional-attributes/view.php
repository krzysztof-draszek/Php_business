<?php

use app\helpers\Translate;
use app\themes\adminlte2\views\yii\widgets\breadcrumbs\Breadcrumbs;
use yii\helpers\Html;
use yii\widgets\DetailView;

?>

<div class="content-wrapper">

    <section class="content-header">
        <h1>
            <?= Html::encode(Translate::_('business', 'View data additional attribute')) ?>
        </h1>
        <?= Breadcrumbs::widget(
            ['links' => isset($this->params['breadcrumbs']) ? $this->params['breadcrumbs'] : [],]
        ) ?>
    </section>

    <section class="content">
        <div class="row">
            <div class="col-xs-12">
                <div class="box">
                    <div class="box-body">

                        <div class="data-additional-attribute-view">

                            <h1><?= Html::encode($this->title) ?></h1>

                            <div class="form-group">
                                <?= Yii::$app->controller->renderPartial(
                                    '@app/themes/adminlte2/views/site/_modalWindow',
                                    [
                                        'modal' => [
                                            'name' => 'cancelFormActionModal',
                                            'header' => Translate::_('business', 'Cancel edit your data'),
                                            'body' => Translate::_(
                                                'business',
                                                'You have chosen to cancel the edit your data task, your changes will not be saved'
                                            ),
                                            'question' => Translate::_(
                                                'business',
                                                'If this is not your intention, please click on \'Continue\'.'
                                            ),
                                            'button' => [
                                                'label' => Translate::_(
                                                    'business',
                                                    'Cancel'
                                                ),
                                                'class' => 'btn btn-back'
                                            ],
                                            'leftButton' => [
                                                'label' => Translate::_('business', 'Cancel'),
                                                'action' => Yii::$app->session->get('urlRedirect'),
                                                'style' => 'btn btn-back',
                                            ],
                                            'rightButton' => [
                                                'label' => Translate::_('business', 'Continue'),
                                                'style' => 'btn btn-primary',
                                                'action' => 'data-dismiss'
                                            ],
                                        ]
                                    ]
                                ); ?>
                                <?= Html::a(
                                    'Update',
                                    [
                                        'update',
                                        'daid' => $model->daid,
                                        'oid' => $model->oid,
                                        'object_type' => $model->object_type
                                    ],
                                    ['class' => 'btn btn-primary']
                                ) ?>
                                <?= Html::a(
                                    'Delete',
                                    [
                                        'delete',
                                        'daid' => $model->daid,
                                        'oid' => $model->oid,
                                        'object_type' => $model->object_type
                                    ],
                                    [
                                        'class' => 'btn btn-danger',
                                        'data' => [
                                            'confirm' => 'Are you sure you want to delete this item?',
                                            'method' => 'post',
                                        ],
                                    ]
                                ) ?>
                            </div>

                            <?= DetailView::widget(
                                [
                                    'model' => $model,
                                    'attributes' => [
                                        'daid',
                                        'oid',
                                        'object_type',
                                        'value',
                                    ],
                                ]
                            ) ?>


                        </div>

                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

