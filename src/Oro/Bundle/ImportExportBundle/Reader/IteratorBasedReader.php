<?php

namespace Oro\Bundle\ImportExportBundle\Reader;

use Oro\Bundle\ImportExportBundle\Exception\LogicException;

/**
 * An item reader that help to pass item in current cursor, move to next or pagination from a query source.
 */
abstract class IteratorBasedReader extends AbstractReader
{
    /**
     * @var \Iterator
     */
    private $sourceIterator;

    /**
     * @var bool
     */
    private $rewound = false;

    /**
     * {@inheritdoc}
     */
    public function read()
    {
        if (null === $this->getSourceIterator()) {
            throw new LogicException('Reader must be configured with source');
        }
        if (!$this->rewound) {
            $this->sourceIterator->rewind();
            $this->rewound = true;
        }

        $result = null;
        if ($this->sourceIterator->valid()) {
            $result  = $this->sourceIterator->current();
            $context = $this->getContext();
            $context->incrementReadOffset();
            $context->incrementReadCount();
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function next(): mixed
    {
        if ($this->sourceIterator->valid()) {
            $this->sourceIterator->next();
        }

        return null;
    }

    /**
     * Setter for iterator
     *
     * @param \Iterator $sourceIterator
     */
    public function setSourceIterator(\Iterator $sourceIterator = null)
    {
        $this->sourceIterator = $sourceIterator;
        $this->rewound        = false;
    }

    /**
     * Getter for iterator
     *
     * @return \Iterator|null
     */
    public function getSourceIterator()
    {
        return $this->sourceIterator;
    }
}
