<?php

namespace Bundle\DoctrinePaginatorBundle\Paginator\Adapter;

use Bundle\DoctrinePaginatorBundle\Paginator\Adapter,
    Symfony\Component\HttpFoundation\Request,
    Symfony\Component\DependencyInjection\ContainerInterface,
    Symfony\Component\EventDispatcher\EventDispatcher,
    Bundle\DoctrinePaginatorBundle\Event\PaginatorEvent,
    Bundle\DoctrinePaginatorBundle\Event\Listener\PaginatorListener;

class Doctrine implements Adapter
{
    protected $strategy = null;
    protected $request = null;
    protected $query = null;
    protected $eventDispatcher = null;
    protected $distinct = true;
    
    /**
     * Total item count
     *
     * @var integer
     */
    protected $rowCount = null;
    
    private $container = null;
    
	/**
     * @param Request - http request
     */
    public function __construct(ContainerInterface $container, Request $request, $strategy)
    {
        $this->request = $request;
        $this->container = $container;
        $this->strategy = $strategy;
        $this->setStrategy($strategy);
    }
    
    public function setStrategy($strategy)
    {
        $this->strategy = $strategy;
        $this->loadStrategy();
    }
    
    private function loadStrategy()
    {
        $this->eventDispatcher = new EventDispatcher();
        $tagName = 'doctrine_paginator.listener.' . $this->strategy;
        foreach ($this->container->findTaggedServiceIds($tagName) as $id => $attributes) {
            $priority = isset($attributes[0]['priority']) ? $attributes[0]['priority'] : 0;
            $this->container->get($id)->subscribe($this->eventDispatcher, $priority);
        }
    }
    
    public function setDistinct($distinct)
    {
        $this->distinct = (bool)$distinct;
    }
    
    public function setQuery($query)
    {
        $this->query = $query;
    }
    
    public function setRowCount($numRows)
    {
        $this->rowCount = $numRows;
    }
    
    public function count()
    {
        if (is_null($this->rowCount)) {
            $eventParams = array(
                'query' => $this->query,
                'distinct' => $this->distinct
            );
            $event = new PaginatorEvent($this, PaginatorListener::EVENT_COUNT, $eventParams);
            $this->eventDispatcher->notifyUntil($event);
            if (!$event->isProcessed()) {
                 throw new \RuntimeException('failure');
            }
            $this->rowCount = $event->getReturnValue();
            //var_dump($this->rowCount);
        }
        return $this->rowCount;
    }
    
	/**
     * @see Zend\Paginator\Adapter:getItems
     */
    public function getItems($offset, $itemCountPerPage)
    {
        $eventParams = array(
            'request' => $this->request,
            'query' => $this->query,
            'distinct' => $this->distinct,
            'offset' => $offset,
            'count' => $itemCountPerPage
        );
        $event = new PaginatorEvent($this, PaginatorListener::EVENT_ITEMS, $eventParams);
        $this->eventDispatcher->notifyUntil($event);
        if (!$event->isProcessed()) {
             throw new \RuntimeException('failure');
        }
        return $event->getReturnValue();
    }
}
