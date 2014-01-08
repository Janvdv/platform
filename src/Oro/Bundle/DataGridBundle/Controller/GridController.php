<?php

namespace Oro\Bundle\DataGridBundle\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;

use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionDispatcher;
use Oro\Bundle\DataGridBundle\Extension\MassAction\MassActionParametersParser;
use Oro\Bundle\ImportExportBundle\Handler\ExportHandler;

class GridController extends Controller
{
    /**
     * @Route("/{gridName}", name="oro_datagrid_index")
     *
     * @param string $gridName
     *
     * @return Response
     */
    public function getAction($gridName)
    {
        $grid   = $this->get('oro_datagrid.datagrid.manager')->getDatagrid($gridName);
        $result = $grid->getData();

        return new JsonResponse($result->toArray());
    }

    /**
     * @Route("/{gridName}/extraAction/{actionName}", name="oro_datagrid_extra_action")
     * @param string $gridName
     * @param string $actionName
     *
     * @return Response
     * @throws \LogicException
     */
    public function extraActionAction($gridName, $actionName)
    {
        $request = $this->getRequest();

        if ($actionName === 'export') {
            $format = $request->query->get('format');
            /** @var ExportHandler $handler */
            $handler = $this->get('oro_importexport.handler.export');

            return $handler->handleExport(
                'datagrid_export_to_' . $format,
                'oro_datagrid',
                $format,
                'oro_datagrid_' . $gridName,
                ['gridName' => $gridName]
            );
        }

        throw new \InvalidArgumentException(sprintf('Unsupported action: %s.', $actionName));
    }

    /**
     * @Route("/{gridName}/massAction/{actionName}", name="oro_datagrid_mass_action")
     * @param string $gridName
     * @param string $actionName
     *
     * @return Response
     * @throws \LogicException
     */
    public function massActionAction($gridName, $actionName)
    {
        $request = $this->getRequest();

        /** @var MassActionParametersParser $massActionParametersParser */
        $parametersParser = $this->get('oro_datagrid.mass_action.parameters_parser');
        $parameters       = $parametersParser->parse($request);

        $requestData = array_merge($request->query->all(), $request->request->all());

        /** @var MassActionDispatcher $massActionDispatcher */
        $massActionDispatcher = $this->get('oro_datagrid.mass_action.dispatcher');
        $response             = $massActionDispatcher->dispatch($gridName, $actionName, $parameters, $requestData);

        $data = [
            'successful' => $response->isSuccessful(),
            'message'    => $response->getMessage(),
        ];

        return new JsonResponse(array_merge($data, $response->getOptions()));
    }
}
