<?php

namespace Oro\Component\Layout\Tests\Unit\Extension\Theme\Generator;

use Oro\Component\Layout\Extension\Theme\Generator\GeneratorData;

class GeneratorDataTest extends \PHPUnit_Framework_TestCase
{
    public function testFilenameShouldBeOptional()
    {
        $data = new GeneratorData(uniqid('testSource', true));
        $this->assertNull($data->getFilename());
    }

    public function testSourceDataGetter()
    {
        $source = uniqid('testSource', true);
        $data   = new GeneratorData($source);

        $this->assertSame($source, $data->getSource());

        $newSource = uniqid('newTestSource', true);
        $data->setSource($newSource);

        $this->assertSame($newSource, $data->getSource());
    }

    public function testFilenameSetter()
    {
        $data = new GeneratorData(uniqid('testSource', true));

        $filename = uniqid('testFilename', true);
        $data->setFilename($filename);

        $this->assertSame($filename, $data->getFilename());

    }
}
