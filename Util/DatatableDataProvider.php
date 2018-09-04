<?php

namespace AppVerk\DatatableBundle\Util;

use Doctrine\ORM\QueryBuilder;
use Knp\Component\Pager\Pagination\SlidingPagination;
use Knp\Component\Pager\Paginator;
use Symfony\Bundle\FrameworkBundle\Templating\EngineInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class DatatableDataProvider
{
    /**
     * Fields templates file declaration
     */
    const TEMPLATE_FIELD_BOOL = 'field_bool';
    const TEMPLATE_FIELD_COLLECTION = 'field_collection';
    const TEMPLATE_FIELD_OBJECT = 'field_object';
    const TEMPLATE_FIELD_TIMESTAMPS = 'field_timestamps';

    /**
     * Other templates
     */
    const TEMPLATE_BUTTON = 'buttons';

    protected $actions = [];

    protected $excludedFields = [];

    protected $fields = [];

    /** @var Paginator */
    private $paginator;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var EngineInterface
     */
    private $templating;

    /**
     * @var Request
     */
    private $request;
    /**
     * @var ConfigProvider
     */
    private $configProvider;

    private $group;

    public function __construct(
        RouterInterface $router,
        Paginator $paginator,
        EngineInterface $templating,
        RequestStack $requestStack,
        ConfigProvider $configProvider
    ) {
        $this->router = $router;
        $this->paginator = $paginator;
        $this->templating = $templating;
        $this->request = $requestStack->getCurrentRequest();
        $this->configProvider = $configProvider;
    }

    public function setGroup($group)
    {
        $this->group = $group;

        return $this;
    }

    /**
     * @param QueryBuilder $query
     * @return array
     */
    public function getData(QueryBuilder $query, $sortData)
    {
        $paginationData = $this->request->get('pagination');
        $page = (int)($paginationData['page'] ?? 1);
        $perpage = (int)($paginationData['perpage'] ?? 10);

        $order = null;
        if ($sortData) {
            $query = $this->addOrderData($query, $sortData);
        }

        $query = $query->getQuery();

        if ($perpage == -1) {
            $perpage = 10000;
        }

        /** @var SlidingPagination $pagination */
        $pagination = $this->paginator->paginate($query, $page, $perpage);

        $data = [
            'meta' => [
                'total'   => $pagination->getTotalItemCount(),
                'page'    => $page,
                'pages'   => $pagination->getPaginationData()['pageCount'],
                'perpage' => $perpage,
            ],
            'data' => $pagination->getItems(),
        ];

        return $data;
    }

    /**
     * @param QueryBuilder $query
     * @param $orderData
     * @return QueryBuilder
     */
    private function addOrderData(QueryBuilder $query, $orderData)
    {
        if ($orderData) {
            $orderDir = $orderData['sort'];
            $alias = $orderData['alias'] ?? $query->getAllAliases()[0];

            $orderField = lcfirst(str_replace("_", '', ucwords($orderData['field'], "_")));

            if ($alias) {
                $sort = sprintf('%s.%s', $alias, $orderField);
            } else {
                $sort = $orderField;
            }
            $query->addOrderBy($sort, $orderDir);
        }

        return $query;
    }
}
