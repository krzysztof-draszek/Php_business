<?php
# * ********************************************************************* *
# *                                                                       *
# *   Business Portal                                                     *
# *   This file is part of business. This project may be found at:        *
# *   https://github.com/IdentityBank/Php_business.                       *
# *                                                                       *
# *   Copyright (C) 2020 by Identity Bank. All Rights Reserved.           *
# *   https://www.identitybank.eu - You belong to you                     *
# *                                                                       *
# *   This program is free software: you can redistribute it and/or       *
# *   modify it under the terms of the GNU Affero General Public          *
# *   License as published by the Free Software Foundation, either        *
# *   version 3 of the License, or (at your option) any later version.    *
# *                                                                       *
# *   This program is distributed in the hope that it will be useful,     *
# *   but WITHOUT ANY WARRANTY; without even the implied warranty of      *
# *   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the        *
# *   GNU Affero General Public License for more details.                 *
# *                                                                       *
# *   You should have received a copy of the GNU Affero General Public    *
# *   License along with this program. If not, see                        *
# *   https://www.gnu.org/licenses/.                                      *
# *                                                                       *
# * ********************************************************************* *

################################################################################
# Namespace                                                                    #
################################################################################

namespace app\controllers;

################################################################################
# Use(s)                                                                       #
################################################################################

use app\helpers\BusinessConfig;
use app\helpers\Translate;
use app\models\IdbBusinessLoginForm;
use Exception;
use idbyii2\components\PortalApi;
use idbyii2\helpers\AccessManager;
use idbyii2\helpers\IdbAccountId;
use idbyii2\helpers\IdbMfaHelper;
use idbyii2\helpers\IdbPortalApiActions;
use idbyii2\helpers\IdbSecurity;
use idbyii2\helpers\IdbYii2Login;
use idbyii2\helpers\Localization;
use idbyii2\helpers\Totp;
use idbyii2\models\db\BusinessAuthlog;
use idbyii2\models\db\BusinessUserData;
use idbyii2\models\idb\IdbBankClientBusiness;
use idbyii2\models\identity\IdbBusinessUser;
use idbyii2\models\identity\IdbUser;
use Yii;
use yii\base\DynamicModel;
use yii\base\Theme;
use yii\helpers\ArrayHelper;
use yii\helpers\Url;

################################################################################
# Class(es)                                                                    #
################################################################################

class SiteController extends IdbController
{

    /**
     * @return string
     */
    public function actionIndex()
    {
        $this->view->params['contextHelpUrl'] = Translate::_(
            'business',
            'https://www.identitybank.eu/help/business/dashboard'
        );

        if (Yii::$app->user->can('organization_user')) {
            $search = Yii::$app->request->post('DynamicModel')['dbName'] ?? null;
            if (Yii::$app->request->post('reset') === 'reset') {
                $search = null;
            }
            $databases = Yii::$app->user->identity->userCurrentDatabases;
            $model = new DynamicModel(['dbName']);
            if (!empty($search)) {
                $model->dbName = $search;
                $databasesSearch = [];
                foreach ($databases as $database) {
                    if (strpos(strtolower($database['name']), strtolower($search)) !== false) {
                        array_push($databasesSearch, $database);
                    }
                }
                $databases = $databasesSearch;
            }
            $params = [
                'params' =>
                    [
                        'contentParams' => compact('databases', 'model')
                    ]
            ];
        } else {
            $params =
                [
                    'content' => '_indexEmpty',
                    'params' => ['contentParams' => null]
                ];
        }

        return $this->render(
            'index',
            $params
        );
    }

    /**
     * @param $dbid
     * @param $action
     *
     * @return \yii\web\Response
     */
    public function actionIdbMenu($dbid, $action)
    {
        if ($dbid !== Yii::$app->user->identity->dbid) {
            AccessManager::changeDatabase(
                Yii::$app->user->identity->id,
                Yii::$app->user->identity->oid,
                Yii::$app->user->identity->aid,
                $dbid
            );
        }

        return $this->redirect([$action]);
    }

    /**
     * @return string
     */
    public function actionMandatoryActions()
    {
        return $this->render('mandatoryActions');
    }

    /**
     * @param null $idbjwt
     *
     * @return mixed
     * @throws \ReflectionException
     * @throws \yii\base\ExitException
     * @throws \yii\base\InvalidConfigException
     */
    public function actionIdbLogin($idbjwt = null)
    {
        return IdbYii2Login::idbLogin($idbjwt, 'business', $this, new IdbBusinessLoginForm());
    }

    /**
     * @param null $post
     *
     * @return string|\yii\web\Response
     * @throws \yii\base\ExitException
     * @throws Exception
     */
    public function actionLogin($post = null)
    {
        if (!Yii::$app->user->isGuest) {
            return $this->goHome();
        }

        Yii::$app->session->destroy();
        $model = new IdbBusinessLoginForm();
        if (empty($post)) {
            $post = Yii::$app->request->post();
        }
        if ($model->load($post) && $model->login()) {
            BusinessAuthlog::login(Yii::$app->user->id);
            if (!empty(Yii::$app->user->getReturnUrl())) {
                if (!empty($post['jwt']) && $post['jwt']) {
                    return $this->goHome();
                } else {
                    return $this->afterLoginRedirect();
                    $this->redirect(Yii::$app->user->getReturnUrl());
                }
                Yii::$app->end();
            }

            return $this->goHome();
        } else {
            $userId = trim($model->userId);
            $accountNumber = trim($model->accountNumber);
            $login = IdbBusinessUser::createLogin(
                $userId,
                $accountNumber
            );
            $userAccount = IdbBusinessUser::findUserAccountByLogin($login);
            if ($userAccount) {
                BusinessAuthlog::findLatestErrors($model);
            }
        }

        return $this->render('login', ['model' => $model]);
    }

    /**
     * @return mixed
     * @throws \Exception
     */
    protected function afterLoginRedirect()
    {
        $user = Yii::$app->user->identity;

        $businessId = IdbAccountId::generateBusinessDbId($user->oid, $user->aid, $user->dbid);

        $modelClient = IdbBankClientBusiness::model($businessId);
        if ($modelClient->getAccountMetadata() == null) {
            return $this->redirect(['/idbdata/idb-data/welcome']);
        }

        return $this->redirect(['/idbdata/idb-data/show-all']);
    }

    /**
     * @return void
     */
    public function actionProfile()
    {
        $this->redirect(Url::to(['/idbuser/profile']));
    }

    /**
     * @param null $post
     *
     * @return string|\yii\web\Response
     * @throws \yii\base\ExitException
     */
    public function actionMfa()
    {
        $model = ['code', 'code_next', 'mfa'];
        $model = new DynamicModel($model);
        $post = Yii::$app->request->post();

        if (
            !empty($post['action'])
            && $post['action'] === 'skip-mfa'
            && BusinessConfig::get()->isMfaSkipEnabled()
        ) {
            $value = IdbUser::createLogin(
                Yii::$app->user->identity->userId,
                Yii::$app->user->identity->accountNumber
            );
            $model->code = $value;
            $model->mfa = json_encode(
                ['type' => 'skip', 'timestamp' => Localization::getDateTimeFileString(), 'value' => $value]
            );
            $modelData = BusinessUserData::instantiate(
                [
                    'uid' => Yii::$app->user->identity->id,
                    'key' => 'mfa',
                    'value' => $model->mfa
                ]
            );
            if (
                $modelData->save()
                && Yii::$app->user->identity->validateMfa($model)
            ) {
                return $this->goHome();
            }
            $model->code = null;
        }

        if (empty(Yii::$app->user->identity->mfa)) {

            $model->addRule(['code', 'code_next'], 'required')
                  ->addRule(['code', 'code_next'], 'string', ['max' => 16])
                  ->addRule(
                      'code',
                      'compare',
                      [
                          'compareAttribute' => 'code_next',
                          'operator' => '!==',
                          'message' => Translate::_('business', "Provide two consecutive authentication codes.")
                      ]
                  )
                  ->addRule(['mfa'], 'string', ['max' => 128]);

            if (
                !empty($post)
                && $model->load($post)
                && !empty($model->mfa)
                && !empty($model->code)
                && !empty($model->code_next)
                && ($model->code !== $model->code_next)
            ) {

                $model->code = preg_replace('/\s+/', "", $model->code);
                $model->code_next = preg_replace('/\s+/', "", $model->code_next);

                if (
                    Totp::verify($model->mfa, $model->code)
                    && Totp::verify($model->mfa, $model->code_next)
                    && ($model->code !== $model->code_next)
                ) {

                    $model->mfa = json_encode(
                        [
                            'type' => 'totp',
                            'timestamp' => Localization::getDateTimeFileString(),
                            'value' => $model->mfa
                        ]
                    );
                    $modelData = BusinessUserData::instantiate(
                        [
                            'uid' => Yii::$app->user->identity->id,
                            'key' => 'mfa',
                            'value' => $model->mfa
                        ]
                    );
                    if (
                        $modelData->save()
                        && Yii::$app->user->identity->validateMfa($model)
                    ) {

                        return $this->goHome();
                    }
                } else {
                    $errorMsg = Translate::_('business', 'Invalid code');
                    $model->addError('code', $errorMsg);
                    $model->addError('code_next', $errorMsg);
                }
            } else {
                $model->mfa = Yii::$app->user->identity->generateMfaSecurityKey();
            }

            Yii::$app->getView()->theme = new Theme(
                [
                    'basePath' => '@app/themes/idb',
                    'baseUrl' => '@web/themes/idb',
                    'pathMap' => [
                        '@app/views' => '@app/themes/idb/views',
                    ],
                ]
            );

            $mfaViewVariables = IdbMfaHelper::getMfaViewVariables($model, BusinessConfig::get());

            if(empty($mfaViewVariables['mfaQr']) || strlen($mfaViewVariables['mfaQr']) == 23){
                $flash = [
                    'subject' => Translate::_('business', 'error'),
                    'message' => Translate::_(
                        'business',
                        'The QR code could not be downloaded. Check your internet connection and try again.'
                    )
                ];

                Yii::$app->session->setFlash('error', $flash);
            }

            return $this->render(
                'createMfa',
                ArrayHelper::merge(
                    ['model' => $model],
                    $mfaViewVariables
                )
            );
        } else {
            $model->addRule(['code'], 'required')
                  ->addRule('code', 'string', ['max' => 16]);
            if (
                BusinessConfig::get()->isMfaSkipEnabled()
                && $model->load(
                    [
                        'DynamicModel' => [
                            'code' => IdbUser::createLogin(
                                Yii::$app->user->identity->userId,
                                Yii::$app->user->identity->accountNumber
                            )
                        ]
                    ]
                )
                && Yii::$app->user->identity->validateMfa($model)
            ) {
                return $this->goHome();
            }
            $model->code = null;

            if (!empty($post)
            ) {
                if (
                    $model->load($post)
                    && Yii::$app->user->identity->validateMfa($model)
                ) {
                    if (BusinessConfig::get()->enabledYii2BusinessAfterLoginStartWithData()) {
                        $user = Yii::$app->user->identity;
                        $businessId = IdbAccountId::generateBusinessDbId($user->oid, $user->aid, $user->dbid);
                        $model = new IdbBankClientBusiness;
                        if ($model->countAllValue($businessId) > 0) {
                            return $this->redirect(['/idbdata/idb-data/show-all']);
                        }
                    }

                    if (!empty(Yii::$app->user->getReturnUrl())) {
                        $this->redirect(Yii::$app->user->getReturnUrl());
                        Yii::$app->end();
                    }

                    return $this->goHome();
                } else {
                    $this->actionLogout();
                }
            } else {
                if (Yii::$app->user->identity->validateMfa()) {
                    return $this->goHome();
                }
            }

            return $this->render('mfa', ['model' => $model]);
        }
    }

    /**
     * @return \yii\web\Response
     */
    public function actionLogout()
    {
        BusinessAuthlog::logout(Yii::$app->user->id);
        Yii::$app->session->destroy();
        Yii::$app->user->logout();

        return $this->goHome();
    }

    /**
     * @return array|false|string|null
     * @throws \yii\base\ExitException
     * @throws \yii\web\NotFoundHttpException
     */
    public function actionIdbApi()
    {
        $portalPeopleApi = PortalApi::getPeopleApi();

        return IdbPortalApiActions::execute($portalPeopleApi, apache_request_headers(), $_REQUEST);
        Yii::$app->end();
    }
}

################################################################################
#                                End of file                                   #
################################################################################
