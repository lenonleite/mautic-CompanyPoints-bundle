<?php

namespace MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller;

use Mautic\CoreBundle\Controller\FormController;
use Mautic\CoreBundle\Factory\PageHelperFactoryInterface;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Entity\CompanyTrigger;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerEventModel;
use MauticPlugin\LeuchtfeuerCompanyPointsBundle\Model\CompanyTriggerModel;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class TriggerController extends FormController
{
    /**
     * @param int $page
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function indexAction(Request $request, PageHelperFactoryInterface $pageHelperFactory, $page = 1)
    {
        // set some permissions
        $permissions = $this->security->isGranted([
            'companypoint:triggers:view',
            'companypoint:triggers:create',
            'companypoint:triggers:edit',
            'companypoint:triggers:delete',
            'companypoint:triggers:publish',
        ], 'RETURN_ARRAY');

        if (!$permissions['companypoint:triggers:view']) {
            return $this->accessDenied();
        }

        $this->setListFilters();

        $pageHelper = $pageHelperFactory->make('mautic.companypoint.trigger', $page);

        $limit      = $pageHelper->getLimit();
        $start      = $pageHelper->getStart();
        $search     = $request->get('search', $request->getSession()->get('mautic.companypoint.trigger.filter', ''));
        $filter     = ['string' => $search, 'force' => []];
        $orderBy    = $request->getSession()->get('mautic.companypoint.trigger.orderby', 't.name');
        $orderByDir = $request->getSession()->get('mautic.companypoint.trigger.orderbydir', 'ASC');
        $triggers   = $this->getModel('companypoint.trigger')->getEntities(
            [
                'start'      => $start,
                'limit'      => $limit,
                'filter'     => $filter,
                'orderBy'    => $orderBy,
                'orderByDir' => $orderByDir,
            ]
        );
        $request->getSession()->set('mautic.companypoint.trigger.filter', $search);
        $count = count($triggers);

        if ($count && $count < ($start + 1)) {
            $lastPage  = $pageHelper->countPage($count);
            $returnUrl = $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $lastPage]);
            $pageHelper->rememberPage($lastPage);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $lastPage],
                'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_pointtrigger_index',
                    'mauticContent' => 'companypointTrigger',
                ],
            ]);
        }

        $pageHelper->rememberPage($page);

        return $this->delegateView([
            'viewParameters' => [
                'searchValue' => $search,
                'items'       => $triggers,
                'page'        => $page,
                'limit'       => $limit,
                'permissions' => $permissions,
                'tmpl'        => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
            ],
            'contentTemplate' => '@LeuchtfeuerCompanyPoints/Trigger/list.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
                'route'         => $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $page]),
            ],
        ]);
    }

    /**
     * View a specific trigger.
     *
     * @param int $objectId
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function viewAction(Request $request, $objectId)
    {
        $entity = $this->getModel('companypoint.trigger')->getEntity($objectId);

        // set the page we came from
        $page = $request->getSession()->get('mautic.companypoint.trigger.page', 1);

        $permissions = $this->security->isGranted([
            'companypoint:triggers:view',
            'companypoint:triggers:create',
            'companypoint:triggers:edit',
            'companypoint:triggers:delete',
            'companypoint:triggers:publish',
        ], 'RETURN_ARRAY');

        if (null === $entity) {
            // set the return URL
            $returnUrl = $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $page]);

            return $this->postActionRedirect([
                'returnUrl'       => $returnUrl,
                'viewParameters'  => ['page' => $page],
                'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
                'passthroughVars' => [
                    'activeLink'    => '#mautic__company_pointtrigger_index',
                    'mauticContent' => 'companypointTrigger',
                ],
                'flashes' => [
                    [
                        'type'    => 'error',
                        'msg'     => 'mautic.companypoint.trigger.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ],
                ],
            ]);
        } elseif (!$permissions['companypoint:triggers:view']) {
            return $this->accessDenied();
        }

        return $this->delegateView([
            'viewParameters' => [
                'entity'      => $entity,
                'page'        => $page,
                'permissions' => $permissions,
            ],
            'contentTemplate' => '@LeuchtfeuerCompanyPoints/Trigger/details.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
                'route'         => $this->generateUrl('mautic_company_pointtrigger_action', [
                    'objectAction' => 'view',
                    'objectId'     => $entity->getId(), ]
                ),
            ],
        ]);
    }

    /**
     * Generates new form and processes post data.
     *
     * @param CompanyTrigger $entity
     *
     * @return JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function newAction(Request $request, $entity = null)
    {
        /** @var CompanyTriggerModel $model */
        $model = $this->getModel('companypoint.trigger');

        if (!($entity instanceof CompanyTrigger)) {
            /** @var CompanyTrigger $entity */
            $entity = $model->getEntity();
        }

        $session      = $request->getSession();
        $pointTrigger = $request->request->get('companypointtrigger') ?? [];
        $sessionId    = $pointTrigger['sessionId'] ?? 'mautic_'.sha1(uniqid(random_int(1, PHP_INT_MAX), true));

        if (!$this->security->isGranted('companypoint:triggers:create')) {
            return $this->accessDenied();
        }

        // set the page we came from
        $page = $request->getSession()->get('mautic.companypoint.trigger.page', 1);

        // set added/updated events
        $addEvents     = $session->get('mautic.companypoint.'.$sessionId.'.triggerevents.modified', []);
        $deletedEvents = $session->get('mautic.companypoint.'.$sessionId.'.triggerevents.deleted', []);

        $action = $this->generateUrl('mautic_company_pointtrigger_action', ['objectAction' => 'new']);

        $form   = $model->createForm($entity, $this->formFactory, $action);
        $form->get('sessionId')->setData($sessionId);

        // /Check for a submitted form and process it
        if ('POST' == $request->getMethod()) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                if ($valid = $this->isFormValid($form)) {
                    // only save events that are not to be deleted
                    $events = array_diff_key($addEvents, array_flip($deletedEvents));

                    // make sure that at least one action is selected
                    if ('companypoint.trigger' == 'point' && empty($events)) {
                        // set the error
                        $form->addError(new FormError(
                            $this->translator->trans('mautic.core.value.required', [], 'validators')
                        ));
                        $valid = false;
                    } else {
                        $model->setEvents($entity, $events);

                        $model->saveEntity($entity);

                        $this->addFlashMessage('mautic.core.notice.created', [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_company_pointtrigger_index',
                            '%url%'       => $this->generateUrl('mautic_company_pointtrigger_action', [
                                'objectAction' => 'edit',
                                'objectId'     => $entity->getId(),
                            ]),
                        ]);

                        if (!$this->getFormButton($form, ['buttons', 'save'])->isClicked()) {
                            // return edit view so that all the session stuff is loaded
                            return $this->editAction($request, $entity->getId(), true);
                        }
                    }
                }
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_company_pointtrigger_index', $viewParameters);
                $template       = 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction';

                // clear temporary fields
                $this->clearSessionComponents($request, $sessionId);

                return $this->postActionRedirect([
                    'returnUrl'       => $returnUrl,
                    'viewParameters'  => $viewParameters,
                    'contentTemplate' => $template,
                    'passthroughVars' => [
                        'activeLink'    => '#mautic_company_pointtrigger_index',
                        'mauticContent' => 'companypointTrigger',
                    ],
                ]);
            }
        } else {
            // clear out existing fields in case the form was refreshed, browser closed, etc
            $this->clearSessionComponents($request, $sessionId);
            $addEvents = $deletedEvents = [];
        }

        return $this->delegateView([
            'viewParameters' => [
                'events'        => $model->getEventGroups(),
                'triggerEvents' => $addEvents,
                'deletedEvents' => $deletedEvents,
                'tmpl'          => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                'entity'        => $entity,
                'form'          => $form->createView(),
                'sessionId'     => $sessionId,
            ],
            'contentTemplate' => '@LeuchtfeuerCompanyPoints/Trigger/form.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
                'route'         => $this->generateUrl('mautic_company_pointtrigger_action', [
                    'objectAction' => (!empty($valid) ? 'edit' : 'new'), // valid means a new form was applied
                    'objectId'     => $entity->getId(), ]
                ),
            ],
        ]);
    }

    /**
     * Generates edit form and processes post data.
     *
     * @param int  $objectId
     * @param bool $ignorePost
     *
     * @return JsonResponse|Response
     */
    public function editAction(Request $request, $objectId, $ignorePost = false)
    {
        /** @var TriggerModel $model */
        $model      = $this->getModel('companypoint.trigger');
        $entity     = $model->getEntity($objectId);
        $session    = $request->getSession();
        $cleanSlate = true;

        // set the page we came from
        $page = $request->getSession()->get('mautic.companypoint.trigger.page', 1);

        // set the return URL
        $returnUrl = $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $page]);

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
            ],
        ];

        // form not found
        if (null === $entity) {
            return $this->postActionRedirect(
                array_merge($postActionVars, [
                    'flashes' => [
                        [
                            'type'    => 'error',
                            'msg'     => 'mautic.companypoint.trigger.error.notfound',
                            'msgVars' => ['%id%' => $objectId],
                        ],
                    ],
                ])
            );
        } elseif (!$this->security->isGranted('companypoint:triggers:edit')) {
            return $this->accessDenied();
        } elseif ($model->isLocked($entity)) {
            // deny access if the entity is locked
            return $this->isLocked($postActionVars, $entity, 'companypoint.trigger');
        }

        $action = $this->generateUrl('mautic_company_pointtrigger_action', ['objectAction' => 'edit', 'objectId' => $objectId]);
        $form   = $model->createForm($entity, $this->formFactory, $action);
        $form->get('sessionId')->setData($objectId);

        // /Check for a submitted form and process it
        if (!$ignorePost && 'POST' == $request->getMethod()) {
            $valid = false;
            if (!$cancelled = $this->isFormCancelled($form)) {
                // set added/updated events
                $addEvents     = $session->get('mautic.companypoint.'.$objectId.'.triggerevents.modified', []);
                $deletedEvents = $session->get('mautic.companypoint.'.$objectId.'.triggerevents.deleted', []);
                $events        = array_diff_key($addEvents, array_flip($deletedEvents));

                if ($valid = $this->isFormValid($form)) {
                    // make sure that at least one field is selected
                    if ('companypoint.trigger' == 'point' && empty($addEvents)) {
                        // set the error
                        $form->addError(new FormError(
                            $this->translator->trans('mautic.core.value.required', [], 'validators')
                        ));
                        $valid = false;
                    } else {
                        $model->setEvents($entity, $events);

                        // form is valid so process the data
                        $model->saveEntity($entity, $this->getFormButton($form, ['buttons', 'save'])->isClicked());

                        // delete entities
                        if (count($deletedEvents)) {
                            $triggerEventModel = $this->getModel('companypoint.triggerevent');
                            \assert($triggerEventModel instanceof CompanyTriggerEventModel);
                            $triggerEventModel->deleteEntities($deletedEvents);
                        }

                        $this->addFlashMessage('mautic.core.notice.updated', [
                            '%name%'      => $entity->getName(),
                            '%menu_link%' => 'mautic_company_pointtrigger_index',
                            '%url%'       => $this->generateUrl('mautic_company_pointtrigger_action', [
                                'objectAction' => 'edit',
                                'objectId'     => $entity->getId(),
                            ]),
                        ]);
                    }
                }
            } else {
                // unlock the entity
                $model->unlockEntity($entity);
            }

            if ($cancelled || ($valid && $this->getFormButton($form, ['buttons', 'save'])->isClicked())) {
                $viewParameters = ['page' => $page];
                $returnUrl      = $this->generateUrl('mautic_company_pointtrigger_index', $viewParameters);
                $template       = 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction';

                // remove fields from session
                $this->clearSessionComponents($request, $objectId);

                return $this->postActionRedirect(
                    array_merge($postActionVars, [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => $viewParameters,
                        'contentTemplate' => $template,
                    ])
                );
            } elseif ($form->get('buttons')->get('apply')->isClicked()) {
                // rebuild everything to include new ids
                $cleanSlate = true;
            }
        } else {
            $cleanSlate = true;

            // lock the entity
            $model->lockEntity($entity);
        }

        if ($cleanSlate) {
            // clean slate
            $this->clearSessionComponents($request, $objectId);

            // load existing events into session
            $triggerEvents   = [];
            $existingActions = $entity->getEvents()->toArray();
            foreach ($existingActions as $a) {
                $id     = $a->getId();
                $action = $a->convertToArray();
                unset($action['form']);
                $triggerEvents[$id] = $action;
            }
            $session->set('mautic.companypoint.'.$objectId.'.triggerevents.modified', $triggerEvents);
            $deletedEvents = [];
        }

        return $this->delegateView([
            'viewParameters' => [
                'events'        => $model->getEventGroups(),
                'triggerEvents' => $triggerEvents,
                'deletedEvents' => $deletedEvents,
                'tmpl'          => $request->isXmlHttpRequest() ? $request->get('tmpl', 'index') : 'index',
                'entity'        => $entity,
                'form'          => $form->createView(),
                'sessionId'     => $objectId,
            ],
            //            'contentTemplate' => '@MauticPoint/Trigger/form.html.twig',
            'contentTemplate' => '@LeuchtfeuerCompanyPoints/Trigger/form.html.twig',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
                'route'         => $this->generateUrl('mautic_company_pointtrigger_action', [
                    'objectAction' => 'edit',
                    'objectId'     => $entity->getId(), ]
                ),
            ],
        ]);
    }

    /**
     * Clone an entity.
     *
     * @param int $objectId
     *
     * @return array|JsonResponse|\Symfony\Component\HttpFoundation\RedirectResponse|Response
     */
    public function cloneAction(Request $request, $objectId)
    {
        $model  = $this->getModel('companypoint.trigger');
        $entity = $model->getEntity($objectId);

        if (null != $entity) {
            if (!$this->security->isGranted('companypoint:triggers:create')) {
                return $this->accessDenied();
            }

            $entity = clone $entity;
            $entity->setIsPublished(false);
        }

        return $this->newAction($request, $entity);
    }

    /**
     * Deletes the entity.
     *
     * @return Response
     */
    public function deleteAction(Request $request, $objectId)
    {
        $page      = $request->getSession()->get('mautic.companypoint.trigger.page', 1);
        $returnUrl = $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
            ],
        ];

        if (Request::METHOD_POST === $request->getMethod()) {
            $model = $this->getModel('companypoint.trigger');
            \assert($model instanceof CompanyTriggerModel);
            $entity = $model->getEntity($objectId);

            if (null === $entity) {
                $flashes[] = [
                    'type'    => 'error',
                    'msg'     => 'mautic.companypoint.trigger.error.notfound',
                    'msgVars' => ['%id%' => $objectId],
                ];
            } elseif (!$this->security->isGranted('companypoint:triggers:delete')) {
                return $this->accessDenied();
            } elseif ($model->isLocked($entity)) {
                return $this->isLocked($postActionVars, $entity, 'companypoint.trigger');
            }

            $model->deleteEntity($entity);

            $identifier = $this->translator->trans($entity->getName());
            $flashes[]  = [
                'type'    => 'notice',
                'msg'     => 'mautic.core.notice.deleted',
                'msgVars' => [
                    '%name%' => $identifier,
                    '%id%'   => $objectId,
                ],
            ];
        } // else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, [
                'flashes' => $flashes,
            ])
        );
    }

    /**
     * Deletes a group of entities.
     *
     * @return Response
     */
    public function batchDeleteAction(Request $request)
    {
        $page      = $request->getSession()->get('mautic.companypoint.trigger.page', 1);
        $returnUrl = $this->generateUrl('mautic_company_pointtrigger_index', ['page' => $page]);
        $flashes   = [];

        $postActionVars = [
            'returnUrl'       => $returnUrl,
            'viewParameters'  => ['page' => $page],
            'contentTemplate' => 'MauticPlugin\LeuchtfeuerCompanyPointsBundle\Controller\TriggerController::indexAction',
            'passthroughVars' => [
                'activeLink'    => '#mautic_company_pointtrigger_index',
                'mauticContent' => 'companypointTrigger',
            ],
        ];

        if (Request::METHOD_POST === $request->getMethod()) {
            $model = $this->getModel('companypoint.trigger');
            \assert($model instanceof CompanyTriggerModel);
            $ids       = json_decode($request->query->get('ids', '{}'));
            $deleteIds = [];

            // Loop over the IDs to perform access checks pre-delete
            foreach ($ids as $objectId) {
                $entity = $model->getEntity($objectId);

                if (null === $entity) {
                    $flashes[] = [
                        'type'    => 'error',
                        'msg'     => 'mautic.companypoint.trigger.error.notfound',
                        'msgVars' => ['%id%' => $objectId],
                    ];
                } elseif (!$this->security->isGranted('companypoint:triggers:delete')) {
                    $flashes[] = $this->accessDenied(true);
                } elseif ($model->isLocked($entity)) {
                    $flashes[] = $this->isLocked($postActionVars, $entity, 'companypoint.trigger', true);
                } else {
                    $deleteIds[] = $objectId;
                }
            }

            // Delete everything we are able to
            if (!empty($deleteIds)) {
                $entities = $model->deleteEntities($deleteIds);

                $flashes[] = [
                    'type'    => 'notice',
                    'msg'     => 'mautic.companypoint.trigger.notice.batch_deleted',
                    'msgVars' => [
                        '%count%' => count($entities),
                    ],
                ];
            }
        } // else don't do anything

        return $this->postActionRedirect(
            array_merge($postActionVars, [
                'flashes' => $flashes,
            ])
        );
    }

    /**
     * Clear field and events from the session.
     */
    private function clearSessionComponents(Request $request, $sessionId): void
    {
        $session = $request->getSession();
        $session->remove('mautic.companypoint.'.$sessionId.'.triggerevents.modified');
        $session->remove('mautic.companypoint.'.$sessionId.'.triggerevents.deleted');
    }
}
