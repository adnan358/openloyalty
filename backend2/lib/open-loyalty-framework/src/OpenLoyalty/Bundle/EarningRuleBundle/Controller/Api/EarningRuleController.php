<?php
/**
 * Copyright © 2017 Divante, Inc. All rights reserved.
 * See LICENSE for license details.
 */
namespace OpenLoyalty\Bundle\EarningRuleBundle\Controller\Api;

use Broadway\CommandHandling\CommandBus;
use FOS\RestBundle\Controller\Annotations\Route;
use FOS\RestBundle\Controller\FOSRestController;
use FOS\RestBundle\View\View;
use Nelmio\ApiDocBundle\Annotation\ApiDoc;
use OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\CreateEarningRuleFormType;
use OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\EarningRulePhotoFormType;
use OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\EditEarningRuleFormType;
use OpenLoyalty\Bundle\EarningRuleBundle\Service\EarningRulePhotoUploader;
use OpenLoyalty\Component\Account\Domain\CustomerId;
use OpenLoyalty\Component\Account\Domain\SystemEvent\AccountSystemEvents;
use OpenLoyalty\Component\Account\Domain\SystemEvent\CustomEventOccurredSystemEvent;
use OpenLoyalty\Component\Customer\Domain\ReadModel\CustomerDetails;
use OpenLoyalty\Component\EarningRule\Domain\Command\ActivateEarningRule;
use OpenLoyalty\Component\EarningRule\Domain\Command\CreateEarningRule;
use OpenLoyalty\Component\EarningRule\Domain\Command\DeactivateEarningRule;
use OpenLoyalty\Component\EarningRule\Domain\Command\RemoveEarningRulePhoto;
use OpenLoyalty\Component\EarningRule\Domain\Command\SetEarningRulePhoto;
use OpenLoyalty\Component\EarningRule\Domain\Command\UpdateEarningRule;
use OpenLoyalty\Component\EarningRule\Domain\Command\UseCustomEventEarningRule;
use OpenLoyalty\Component\EarningRule\Domain\EarningRule;
use OpenLoyalty\Component\EarningRule\Domain\EarningRuleId;
use OpenLoyalty\Component\EarningRule\Domain\Exception\CustomEventEarningRuleAlreadyExistsException;
use OpenLoyalty\Component\EarningRule\Domain\Model\UsageSubject;
use OpenLoyalty\Component\Account\Infrastructure\Exception\EarningRuleLimitExceededException;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use OpenLoyalty\Bundle\EarningRuleBundle\Model\EarningRule as BundleEarningRule;

/**
 * Class EarningRuleController.
 */
class EarningRuleController extends FOSRestController
{
    /**
     * Method allow to create new earning rule.
     *
     * @Route(name="oloy.earning_rule.create", path="/earningRule")
     * @Method("POST")
     * @Security("is_granted('CREATE_EARNING_RULE')")
     * @ApiDoc(
     *     name="Create new Earning rule",
     *     section="Earning Rule",
     *     input={"class" = "OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\CreateEarningRuleFormType", "name" = "earningRule"},
     *     statusCodes={
     *       200="Returned when successful",
     *       400="Returned when form contains errors"
     *     }
     * )
     *
     * @param Request $request
     *
     * @return \FOS\RestBundle\View\View
     */
    public function createAction(Request $request)
    {
        $form = $this->get('form.factory')->createNamed('earningRule', CreateEarningRuleFormType::class);
        $uuidGenerator = $this->get('broadway.uuid.generator');

        /** @var CommandBus $commandBus */
        $commandBus = $this->get('broadway.command_handling.command_bus');

        $form->handleRequest($request);

        if ($form->isValid()) {
            /** @var \OpenLoyalty\Bundle\EarningRuleBundle\Model\EarningRule $data */
            $data = $form->getData();
            $id = new EarningRuleId($uuidGenerator->generate());

            try {
                $commandBus->dispatch(
                    new CreateEarningRule($id, $data->getType(), $data->toArray())
                );
            } catch (CustomEventEarningRuleAlreadyExistsException $e) {
                $form->get('eventName')->addError(new FormError($e->getMessage()));

                return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
            }

            return $this->view(['earningRuleId' => $id->__toString()]);
        }

        return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Edit existing earning rule.
     *
     * @Route(name="oloy.earning_rule.edit", path="/earningRule/{earningRule}")
     * @Method("PUT")
     * @Security("is_granted('EDIT', earningRule)")
     * @ApiDoc(
     *     name="Edit Earning rule",
     *     section="Earning Rule",
     *     input={"class" = "OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\EditEarningRuleFormType", "name" = "earningRule"},
     *     statusCodes={
     *       200="Returned when successful",
     *       400="Returned when form contains errors",
     *       404="Returned when earning rule does not exist"
     *     }
     * )
     *
     * @param Request     $request
     * @param EarningRule $earningRule
     *
     * @return \FOS\RestBundle\View\View
     */
    public function editAction(Request $request, EarningRule $earningRule)
    {
        $model = BundleEarningRule::createFromDomain($earningRule);

        $form = $this->get('form.factory')
            ->createNamed(
                'earningRule',
                EditEarningRuleFormType::class,
                $model,
                [
                    'type' => array_flip(EarningRule::TYPE_MAP)[get_class($earningRule)],
                    'method' => 'PUT',
                ]
            );

        /** @var CommandBus $commandBus */
        $commandBus = $this->get('broadway.command_handling.command_bus');

        $form->handleRequest($request);

        if ($form->isValid()) {
            /** @var \OpenLoyalty\Bundle\EarningRuleBundle\Model\EarningRule $data */
            $data = $form->getData();

            try {
                $commandBus->dispatch(
                    new UpdateEarningRule($earningRule->getEarningRuleId(), $data->toArray())
                );
            } catch (CustomEventEarningRuleAlreadyExistsException $e) {
                $form->get('eventName')->addError(new FormError($e->getMessage()));

                return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
            }

            return $this->view(['earningRuleId' => $earningRule->getEarningRuleId()->__toString()]);
        }

        return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Method will return earning rule details.
     *
     * @Route(name="oloy.earning_rule.get", path="/earningRule/{earningRule}")
     * @Route(name="oloy.earning_rule.seller.get", path="/seller/earningRule/{earningRule}")
     * @Method("GET")
     * @Security("is_granted('VIEW', earningRule)")
     * @ApiDoc(
     *     name="get Earning rule",
     *     section="Earning Rule",
     *     statusCodes={
     *       200="Returned when successful",
     *       404="Returned when earning rule does not exist"
     *     }
     * )
     *
     * @param EarningRule $earningRule
     *
     * @return \FOS\RestBundle\View\View
     */
    public function getAction(EarningRule $earningRule)
    {
        return $this->view($earningRule);
    }

    /**
     * Method will return a complete list of earning rules.
     *
     * @Route(name="oloy.earning_rule.list", path="/earningRule")
     * @Route(name="oloy.earning_rule.seller.list", path="/seller/earningRule")
     * @Method("GET")
     * @Security("is_granted('LIST_ALL_EARNING_RULES')")
     *
     * @ApiDoc(
     *     name="get earning rules list",
     *     section="Earning Rule",
     *     parameters={
     *      {"name"="active", "dataType"="boolean", "required"=false, "description"="Return only active or inactive earning rules"},
     *      {"name"="page", "dataType"="integer", "required"=false, "description"="Page number"},
     *      {"name"="perPage", "dataType"="integer", "required"=false, "description"="Number of elements per page"},
     *      {"name"="sort", "dataType"="string", "required"=false, "description"="Field to sort by"},
     *      {"name"="direction", "dataType"="asc|desc", "required"=false, "description"="Sorting direction"},
     *     }
     * )
     *
     * @param Request $request
     *
     * @return \FOS\RestBundle\View\View
     */
    public function getListAction(Request $request)
    {
        $pagination = $this->get('oloy.pagination')->handleFromRequest($request);

        $earningRuleRepository = $this->get('oloy.earning_rule.repository');
        $rulesQb = $earningRuleRepository
            ->findAllPaginated(
                $pagination->getPage(),
                $pagination->getPerPage(),
                $pagination->getSort(),
                $pagination->getSortDirection(),
                true
            );
        $totalQb = $earningRuleRepository->countTotal(true);

        if ($request->query->has('active')) {
            $active = $request->get('active', null);
            if ($active == true) {
                $totalQb->andWhere($totalQb->getRootAliases()[0].'.active = true');
                $rulesQb->andWhere($totalQb->getRootAliases()[0].'.active = true');
            } elseif ($active == false) {
                $totalQb->andWhere($totalQb->getRootAliases()[0].'.active = false');
                $rulesQb->andWhere($totalQb->getRootAliases()[0].'.active = false');
            }
        }

        return $this->view(
            [
                'earningRules' => $rulesQb->getQuery()->getResult(),
                'total' => $totalQb->getQuery()->getSingleScalarResult(),
            ],
            200
        );
    }

    /**
     * Activate or deactivate earning rule.
     *
     * @Route(name="oloy.earning_rule.activate", path="/earningRule/{earningRule}/activate")
     * @Method("POST")
     * @Security("is_granted('ACTIVATE', earningRule)")
     *
     * @ApiDoc(
     *     name="activate/deactivate earningRule",
     *     section="Earning Rule",
     *     parameters={{"name"="active", "dataType"="boolean", "required"=true}},
     *     statusCodes={
     *       200="Returned when successful",
     *       400="Returned when active parameter is not present",
     *       404="Returned when earning rule does not exist"
     *     }
     * )
     *
     * @param Request     $request
     * @param EarningRule $earningRule
     *
     * @return \FOS\RestBundle\View\View
     */
    public function activateEarningAction(Request $request, EarningRule $earningRule)
    {
        $activate = $request->request->get('active', null);
        if (null === $activate) {
            return $this->view(['active' => 'this field is required'], Response::HTTP_BAD_REQUEST);
        }

        $commandBus = $this->get('broadway.command_handling.command_bus');

        if ($activate) {
            $commandBus->dispatch(new ActivateEarningRule($earningRule->getEarningRuleId()));
        } else {
            $commandBus->dispatch(new DeactivateEarningRule($earningRule->getEarningRuleId()));
        }

        return $this->view();
    }

    /**
     * This method allows to use a custom event earning rule.<br/>
     * All you need to do is call this api endpoint with proper parameters.
     *
     * @Route(name="oloy.earning_rule.report_custom_event", path="/{version}/earnRule/{eventName}/customer/{customer}", requirements={"version": "v1"}, defaults={"version":"v1"})
     * @Method("POST")
     *
     * @ApiDoc(
     *     name="report custom event and earn points",
     *     section="Earning Rule",
     *     parameters={{"name"="event_name", "dataType":"string", "required":true}},
     *     requirements={{"name"="version", "description"="api version, v1 required", "default":"v1"}},
     *     statusCodes={
     *       200="Returned when successful",
     *       400="Returned when earning rule for event does not exist or limit was exceeded. Additional info provided in response.",
     *       404="Returned when customer does not exist"
     *     }
     *
     * )
     *
     * @param $eventName
     * @param CustomerDetails $customer
     *
     * @return \FOS\RestBundle\View\View
     */
    public function reportCustomEventAction($eventName, CustomerDetails $customer)
    {
        $event = new CustomEventOccurredSystemEvent(
            new CustomerId($customer->getCustomerId()->__toString()),
            $eventName
        );

        try {
            $this->get('broadway.event_dispatcher')->dispatch(
                AccountSystemEvents::CUSTOM_EVENT_OCCURRED,
                [$event]
            );
        } catch (EarningRuleLimitExceededException $e) {
            return $this->view(['error' => 'limit exceeded'], Response::HTTP_BAD_REQUEST);
        }

        if ($event->getEvaluationResult() === null) {
            return $this->view(['error' => 'event does not exist'], Response::HTTP_BAD_REQUEST);
        }

        $this->get('broadway.command_handling.command_bus')
            ->dispatch(new UseCustomEventEarningRule(
                new EarningRuleId($event->getEvaluationResult()->getEarningRuleId()),
                new UsageSubject($customer->getCustomerId()->__toString())
            ));

        return $this->view(['points' => $event->getEvaluationResult()->getPoints()], Response::HTTP_OK);
    }

    /**
     * Add photo to earning rule.
     *
     * @Route(name="oloy.earning_rule.add_photo", path="/earningRule/{earningRule}/photo")
     * @Method("POST")
     * @Security("is_granted('EDIT', earningRule)")
     * @ApiDoc(
     *     name="Add photo to earning rule",
     *     section="Earning Rule",
     *     input={"class" = "OpenLoyalty\Bundle\EarningRuleBundle\Form\Type\EarningRulePhotoFormType", "name" = "photo"}
     * )
     *
     * @param Request     $request
     * @param EarningRule $earningRule
     *
     * @return View
     */
    public function addPhotoAction(Request $request, EarningRule $earningRule)
    {
        /** @var EarningRulePhotoFormType $form */
        $form = $this->get('form.factory')->createNamed('photo', EarningRulePhotoFormType::class);
        $form->handleRequest($request);

        if ($form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->getData()->getFile();
            /** @var EarningRulePhotoUploader $uploader */
            $uploader = $this->get('oloy.earning_rule.photo_uploader');

            try {
                $uploader->remove($earningRule->getEarningRulePhoto());
                $photo = $uploader->upload($file);
                $command = new SetEarningRulePhoto($earningRule->getEarningRuleId(), $photo);
                $this->get('broadway.command_handling.command_bus')->dispatch($command);

                return $this->view([], Response::HTTP_OK);
            } catch (\Exception $ex) {
                return $this->view($ex->getMessage(), Response::HTTP_BAD_REQUEST);
            }
        }

        return $this->view($form->getErrors(), Response::HTTP_BAD_REQUEST);
    }

    /**
     * Remove photo from earning rule.
     *
     * @Route(name="oloy.earning_rule.remove_photo", path="/earningRule/{earningRule}/photo")
     * @Method("DELETE")
     * @Security("is_granted('EDIT', earningRule)")
     * @ApiDoc(
     *     name="Delete photo from Earning rule",
     *     section="Earning Rule"
     * )
     *
     * @param EarningRule $earningRule
     *
     * @return View
     */
    public function removePhotoAction(EarningRule $earningRule)
    {
        $uploader = $this->get('oloy.earning_rule.photo_uploader');
        $uploader->remove($earningRule->getEarningRulePhoto());

        $command = new RemoveEarningRulePhoto($earningRule->getEarningRuleId());
        try {
            $this->get('broadway.command_handling.command_bus')->dispatch($command);
        } catch (\Exception $ex) {
            return $this->view($ex->getMessage(), Response::HTTP_BAD_REQUEST);
        }

        return $this->view([], Response::HTTP_OK);
    }

    /**
     * Get earning rule photo.
     *
     * @Route(name="oloy.earning_rule.get_photo", path="/earningRule/{earningRule}/photo")
     * @Method("GET")
     * @ApiDoc(
     *     name="Get earning rule photo",
     *     section="Earning Rule"
     * )
     *
     * @param EarningRule $earningRule
     *
     * @return Response
     */
    public function getPhotoAction(EarningRule $earningRule)
    {
        $photo = $earningRule->getEarningRulePhoto();
        if (!$photo) {
            throw $this->createNotFoundException();
        }
        $content = $this->get('oloy.earning_rule.photo_uploader')->get($photo);
        if (!$content) {
            throw $this->createNotFoundException();
        }

        $response = new Response($content);
        $response->headers->set('Content-Disposition', 'inline');
        $response->headers->set('Content-Type', $photo->getMime());

        return $response;
    }
}
