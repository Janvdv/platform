<?php

namespace Oro\Bundle\ActionBundle\Tests\Unit\Action;

use Oro\Bundle\ActionBundle\Action\FormatName;
use Oro\Bundle\EntityBundle\Provider\EntityNameResolver;
use Oro\Component\ConfigExpression\ContextAccessor;
use Symfony\Component\EventDispatcher\EventDispatcher;

class FormatNameTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var FormatName
     */
    protected $action;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|ContextAccessor
     */
    protected $contextAccessor;

    /**
     * @var \PHPUnit\Framework\MockObject\MockObject|EntityNameResolver
     */
    protected $entityNameResolver;

    protected function setUp(): void
    {
        $this->contextAccessor = $this->getMockBuilder('Oro\Component\ConfigExpression\ContextAccessor')
            ->disableOriginalConstructor()
            ->getMock();
        $this->entityNameResolver = $this->getMockBuilder('Oro\Bundle\EntityBundle\Provider\EntityNameResolver')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action = new FormatName($this->contextAccessor, $this->entityNameResolver);

        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getMockBuilder('Symfony\Component\EventDispatcher\EventDispatcher')
            ->disableOriginalConstructor()
            ->getMock();
        $this->action->setDispatcher($dispatcher);
    }

    /**
     * @expectedException \Oro\Component\Action\Exception\InvalidParameterException
     * @expectedExceptionMessage Object parameter is required
     */
    public function testInitializeExceptionNoObject()
    {
        $this->action->initialize(array('attribute' => $this->getPropertyPath()));
    }

    /**
     * @expectedException \Oro\Component\Action\Exception\InvalidParameterException
     * @expectedExceptionMessage Attribute name parameter is required
     */
    public function testInitializeExceptionNoAttribute()
    {
        $this->action->initialize(array('object' => new \stdClass()));
    }

    public function testInitialize()
    {
        $options = array('object' => new \stdClass(), 'attribute' => $this->getPropertyPath());
        $this->assertEquals($this->action, $this->action->initialize($options));
        $this->assertAttributeEquals($options, 'options', $this->action);
    }

    public function testExecute()
    {
        $object = new \stdClass();
        $attribute = $this->getPropertyPath();
        $context = array();
        $options = array('object' => $object, 'attribute' => $attribute);
        $this->assertEquals($this->action, $this->action->initialize($options));
        $this->entityNameResolver->expects($this->once())
            ->method('getName')
            ->with($object)
            ->will($this->returnValue('FORMATTED'));
        $this->contextAccessor->expects($this->once())
            ->method('setValue')
            ->with($context, $attribute, 'FORMATTED');
        $this->contextAccessor->expects($this->once())
            ->method('getValue')
            ->with($context, $object)
            ->will($this->returnArgument(1));
        $this->action->execute($context);
    }

    protected function getPropertyPath()
    {
        return $this->getMockBuilder('Symfony\Component\PropertyAccess\PropertyPath')
            ->disableOriginalConstructor()
            ->getMock();
    }
}
