<?php

namespace Pim\Bundle\EnrichBundle\Controller;

use Akeneo\Bundle\BatchBundle\Job\JobParameters;
use Akeneo\Bundle\BatchBundle\Launch\JobLauncher;
use Doctrine\Common\Persistence\ManagerRegistry;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Pim\Bundle\DataGridBundle\Adapter\GridFilterAdapterInterface;
use Pim\Bundle\DataGridBundle\Extension\MassAction\MassActionDispatcher;
use Pim\Bundle\EnrichBundle\AbstractController\AbstractDoctrineController;
use Pim\Bundle\EnrichBundle\Form\Type\MassEditOperatorType;
use Pim\Bundle\EnrichBundle\MassEditAction\Operator\AbstractMassEditOperator;
use Pim\Bundle\EnrichBundle\MassEditAction\OperatorRegistry;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\SecurityContextInterface;
use Symfony\Component\Translation\TranslatorInterface;
use Symfony\Component\Validator\ValidatorInterface;

/**
 * Mass edit operation controller
 *
 * @author    Gildas Quemener <gildas@akeneo.com>
 * @copyright 2013 Akeneo SAS (http://www.akeneo.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MassEditActionController extends AbstractDoctrineController
{
    /** @var AbstractMassEditOperator */
    protected $operator;

    /** @var MassActionParametersParser */
    protected $parametersParser;

    /** @var MassActionDispatcher */
    protected $massActionDispatcher;

    /** @var ValidatorInterface */
    protected $validator;

    /** @var array */
    protected $objects;

    /** @var GridFilterAdapterInterface */
    protected $gridFilterAdapter;

    /** @var JobLauncher */
    protected $jobLauncher;

    /**
     * Constructor
     *
     * @param Request                    $request
     * @param EngineInterface            $templating
     * @param RouterInterface            $router
     * @param SecurityContextInterface   $securityContext
     * @param FormFactoryInterface       $formFactory
     * @param ValidatorInterface         $validator
     * @param TranslatorInterface        $translator
     * @param EventDispatcherInterface   $eventDispatcher
     * @param ManagerRegistry            $doctrine
     * @param OperatorRegistry           $operatorRegistry
     * @param MassActionParametersParser $parametersParser
     * @param MassActionDispatcher       $massActionDispatcher
     * @param GridFilterAdapterInterface $gridFilterAdapter
     * @param MassEditJobManager         $massEditJobManager
     */
    public function __construct(
        Request $request,
        EngineInterface $templating,
        RouterInterface $router,
        SecurityContextInterface $securityContext,
        FormFactoryInterface $formFactory,
        ValidatorInterface $validator,
        TranslatorInterface $translator,
        EventDispatcherInterface $eventDispatcher,
        ManagerRegistry $doctrine,
        OperatorRegistry $operatorRegistry,
        MassActionParametersParser $parametersParser,
        MassActionDispatcher $massActionDispatcher,
        GridFilterAdapterInterface $gridFilterAdapter,
        JobLauncher $jobLauncher
    ) {
        parent::__construct(
            $request,
            $templating,
            $router,
            $securityContext,
            $formFactory,
            $validator,
            $translator,
            $eventDispatcher,
            $doctrine
        );

        $this->operatorRegistry     = $operatorRegistry;
        $this->parametersParser     = $parametersParser;
        $this->massActionDispatcher = $massActionDispatcher; // TODO: to remove
        $this->gridFilterAdapter    = $gridFilterAdapter;
        $this->jobLauncher          = $jobLauncher;
    }

    /**
     * @Template
     * @AclAncestor("pim_enrich_mass_edit")
     *
     * @return array
     */
    public function chooseAction()
    {
        $operator = $this->operatorRegistry->getOperator(
            $this->request->get('gridName')
        );

        $form = $this->getOperatorForm($operator);

        if ($this->request->isMethod('POST')) {
            $form->submit($this->request);
            if ($form->isValid()) {
                return $this->redirectToRoute(
                    'pim_enrich_mass_edit_action_configure',
                    $this->getQueryParams() + ['operationAlias' => $operator->getOperationAlias()]
                );
            }
        }

        return [
            'form' => $form->createView(),
            'count' => $this->getObjectCount(),
            'queryParams' => $this->getQueryParams(),
            'operator' => $operator,
        ];
    }

    /**
     * @param string $operationAlias
     *
     * @AclAncestor("pim_enrich_mass_edit")
     * @throws NotFoundHttpException
     * @return Response|RedirectResponse
     */
    public function configureAction($operationAlias)
    {
        try {
            $operator = $this->operatorRegistry->getOperator(
                $this->request->get('gridName')
            );

            $operator
                ->setOperationAlias($operationAlias);
        } catch (\InvalidArgumentException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }

        $operator->initializeOperation();
        $form = $this->getOperatorForm($operator);

        if ($this->request->isMethod('POST')) {
            $form->submit($this->request);
            $operator->initializeOperation();
            $form = $this->getOperatorForm($operator);
        }

        return $this->render(
            sprintf('PimEnrichBundle:MassEditAction:configure/%s.html.twig', $operationAlias),
            [
                'form'         => $form->createView(),
                'operator'     => $operator,
                'productCount' => $this->getObjectCount(),
                'queryParams'  => $this->getQueryParams()
            ]
        );
    }

    /**
     * @param string $operationAlias
     *
     * @AclAncestor("pim_enrich_mass_edit")
     * @throws NotFoundHttpException
     * @return Response|RedirectResponse
     */
    public function performAction($operationAlias)
    {
        try {
            $operator = $this->operatorRegistry->getOperator(
                $this->request->get('gridName')
            );

            $operator
                ->setOperationAlias($operationAlias);
        } catch (\InvalidArgumentException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }

        $operator->initializeOperation();
        $form = $this->getOperatorForm($operator, ['Default', 'configureAction']);
        $form->submit($this->request);

        if ($form->isValid()) {

            $operator->getOperation()->saveConfiguration();

            // TODO : put validator in JobParametersValidator ?
            // Binding does not actually perform the operation, thus form errors can miss some constraints
            foreach ($this->validator->validate($operator) as $violation) {
                $form->addError(
                    new FormError(
                        $violation->getMessage(),
                        $violation->getMessageTemplate(),
                        $violation->getMessageParameters(),
                        $violation->getMessagePluralization()
                    )
                );
            }
        }

        if ($form->isValid()) {

            // TODO : put JobParameter array generation in the operation
            $pimFilters = $this->gridFilterAdapter->transform($this->request);
            $operationConfiguration = json_encode($operator->getOperation()->getConfiguration());

            $massEditParamaters = [
                'operationAlias' => $operationAlias,
                'gridName'       => $this->request->get('gridName'),
                'filters'        => json_encode($pimFilters),
                'operations'     => $operationConfiguration
            ];
            $jobParamaters = new JobParameters();
            $jobParamaters->setJobParameters($massEditParamaters);

            $this->jobLauncher->run($this->job, $jobParamaters);

            $this->addFlash(
                'success',
                sprintf('pim_enrich.mass_edit_action.%s.launched_flash', $operationAlias)
            );

            return $this->redirectToRoute($operator->getPerformedOperationRedirectionRoute());
        }

        return $this->render(
            sprintf('PimEnrichBundle:MassEditAction:configure/%s.html.twig', $operationAlias),
            [
                'form'         => $form->createView(),
                'operator'     => $operator,
                'productCount' => $this->getObjectCount(),
                'queryParams'  => $this->getQueryParams()
            ]
        );
    }

    /**
     * @param AbstractMassEditOperator $operator
     * @param array                    $validationGroups
     *
     * @return Form
     */
    protected function getOperatorForm(AbstractMassEditOperator $operator, array $validationGroups = [])
    {
        return $this->createForm(
            new MassEditOperatorType(),
            $operator,
            [
                'operations' => $operator->getOperationChoices(),
                'validation_groups' => $validationGroups
            ]
        );
    }

    /**
     * Get the count of objects to perform the mass action on
     *
     * @return integer
     */
    protected function getObjectCount()
    {
        return count($this->getObjects());
    }

    /**
     * Get the datagrid query parameters
     *
     * @return array
     */
    protected function getQueryParams()
    {
        $params = $this->parametersParser->parse($this->request);

        $params['gridName']   = $this->request->get('gridName');
        $params['actionName'] = $this->request->get('actionName');
        $params['values']     = implode(',', $params['values']);
        $params['filters']    = json_encode($params['filters']);
        $params['dataLocale'] = $this->request->get('dataLocale', null);

        return $params;
    }

    /**
     * Dispatch mass action
     */
    protected function dispatchMassAction()
    {
        $this->objects = $this->massActionDispatcher->dispatch($this->request);
    }

    /**
     * Get products to mass edit
     *
     * @return array
     */
    protected function getObjects()
    {
        if ($this->objects === null) {
            $this->dispatchMassAction();
        }

        return $this->objects;
    }
}
