<?php

namespace AppVerk\DatatableBundle\Util;

use AppVerk\Components\Doctrine\DeletableInterface;
use AppVerk\Components\Doctrine\EntityInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\PersistentCollection;
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
    const TEMPLATE_FIELD_BOOL = 'AdminBundle:metronic/datatables/fields:bool.html.twig';
    const TEMPLATE_FIELD_COLLECTION = 'AdminBundle:metronic/datatables/fields:collection.html.twig';
    const TEMPLATE_FIELD_OBJECT = 'AdminBundle:metronic/datatables/fields:object.html.twig';
    const TEMPLATE_FIELD_TIMESTAMPS = 'AdminBundle:metronic/datatables/fields:timestamps.html.twig';

    /**
     * Other templates
     */
    const TEMPLATE_BUTTON = 'AdminBundle:metronic/datatables/buttons:%type%.html.twig';

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
    }

    /**
     * @param QueryBuilder $query
     * @param int $recordsTotal
     * @param array $actions
     * @param array $fields
     * @return array
     */
    public function getData(QueryBuilder $query, int $recordsTotal, array $actions = [], array $fields = [], $groupActions = false)
    {
        $start = (int)$this->request->get('start');
        $limit = (int)$this->request->get('length');

        $order = null;
        $orderData = $this->request->get('order');
        if ($orderData) {
            $query = $this->addOrderData($query, $fields, $orderData, $groupActions);
        }

        $query = $query->getQuery()->setCacheable(true)->setCacheMode(ClassMetadata::CACHE_USAGE_NONSTRICT_READ_WRITE);

        $this->actions = $actions;
        $this->fields = $fields;

        $page = ($start > 0) ? $start / 10 + 1 : 1;

        if ($limit == -1) {
            $limit = 10000;
        }
        if (!$limit) {
            $limit = 10;
        }
        /** @var SlidingPagination $pagination */
        $pagination = $this->paginator->paginate($query, $page, $limit);

        $items = $this->prepareItems($pagination->getItems(), $groupActions);

        $data = [
            'recordsTotal'    => $recordsTotal,
            'recordsFiltered' => $pagination->getTotalItemCount(),
            'data'            => $items,
        ];

        return $data;
    }

    private function prepareItems(array $items, $groupActions)
    {
        $data = [];
        foreach ($items as $object) {
            $row = ($groupActions) ? [$this->renderGroupCheckbox($object->getId())] : [];
            foreach ($this->fields as $field) {
                $fieldUpper = ucfirst($field);
                $getter = "get{$fieldUpper}";
                if (method_exists($object, $getter)) {
                    $row = $this->renderValue($getter, $object, $row);
                }
                $boolGetter = "is{$fieldUpper}";
                if (method_exists($object, $boolGetter)) {
                    $row = $this->renderValue($boolGetter, $object, $row);
                }
            }
            $row[] = ($this->actions) ? $this->renderActions($object) : null;
            $data[] = array_values($row);
        }

        return array_values($data);
    }

    /**
     * @param $getter
     * @param EntityInterface $object
     * @param $row
     * @return array
     */
    private function renderValue($getter, EntityInterface $object, $row): array
    {
        $value = $object->$getter();
        if (is_bool($value)) {
            $row[] = $this->renderBoolValue($value);
        } elseif ($value instanceof \DateTime) {
            $row[] = $this->renderDateTime($value);
        } elseif ($value instanceof PersistentCollection) {
            $row[] = $this->renderCollection($value);
        } elseif (is_object($value)) {
            $row[] = $this->renderObject($value);
        } else {
            $row[] = $value;
        }

        return $row;
    }

    private function renderBoolValue($item)
    {
        return $this->templating->render(self::TEMPLATE_FIELD_BOOL, ['item' => $item]);
    }

    private function renderDateTime(\DateTime $dateTime)
    {
        return $dateTime->format('d-m-Y H:i');
    }

    private function renderCollection(PersistentCollection $collection)
    {
        return $this->templating->render(self::TEMPLATE_FIELD_COLLECTION, ['collection' => $collection]);
    }

    private function renderObject($object)
    {
        return $this->templating->render(self::TEMPLATE_FIELD_OBJECT, ['object' => $object]);
    }

    private function renderActions(EntityInterface $object)
    {
        $field = '';
        $actions = $this->actions;

        foreach ($actions as $name => $data) {
            if ($name == 'delete' && $object instanceof DeletableInterface && $object->isDeletable() === false) {
                continue;
            }
            if (isset($data['template'])) {
                $field .= $this->templating->render(
                    $data['template'],
                    ['object' => $object]
                );
            } else {
                $params = [];
                foreach ($data['params'] as $param){
                    $paramsArray = explode('.', $param['value']);

                    if(isset($paramsArray[1])){
                        $fieldUpperFirst = ucfirst($paramsArray[0]);
                        $getterFirst = "get{$fieldUpperFirst}";
                        $fieldUpperSecond = ucfirst($paramsArray[1]);
                        $getterSecond = "get{$fieldUpperSecond}";
                        $value = $object->$getterFirst()->$getterSecond();
                    }else{
                        $fieldUpper = ucfirst($param['value']);
                        $getter = "get{$fieldUpper}";
                        $value = (method_exists($object, $getter)) ? $object->$getter() : $param['value'];
                    }

                    $params[$param['key']] = $value;
                }

                $url = $this->router->generate(
                    $data['route'],
                    $params
                );

                $field .= $this->templating->render(
                    str_replace("%type%", $name, self::TEMPLATE_BUTTON),
                    ['url' => $url]
                );
            }
        }

        return $field;
    }

    /**
     * @param QueryBuilder $query
     * @param array $fields
     * @param $orderData
     * @return QueryBuilder
     */
    private function addOrderData(QueryBuilder $query, array $fields, $orderData, $groupActions)
    {
        $orderField = null;
        foreach ($fields as $index => $field) {
            if($groupActions){
                if ($orderData[0]['column'] == $index+1) {
                    $orderField = $field;
                }
            }else{
                if ($orderData[0]['column'] == $index) {
                    $orderField = $field;
                }
            }
        }
        if ($orderField) {
            $orderDir = $orderData[0]['dir'];
            $aliases = $query->getAllAliases();
            $alias = $aliases[0];
            $query->addOrderBy("$alias.$orderField", $orderDir);
        }

        return $query;
    }

    private function renderGroupCheckbox($objectId)
    {
        return '<td><label class="mt-checkbox mt-checkbox-single mt-checkbox-outline"><input name="id[]" type="checkbox" class="checkboxes" value="'.$objectId.'"><span></span></label></td>';
    }
}
