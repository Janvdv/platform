<?php

namespace Oro\Bundle\SidebarBundle\Migrations\Data\ORM;

use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Oro\Bundle\OrganizationBundle\Migrations\Data\ORM\UpdateWithOrganization;
use Oro\Bundle\SidebarBundle\Entity\Widget;

/**
 * Assign exists sidebar widgets to the default organization
 */
class UpdateSidebarWidgetsWithOrganization extends UpdateWithOrganization implements DependentFixtureInterface
{
    /**
     * {@inheritdoc}
     */
    public function getDependencies()
    {
        return ['Oro\Bundle\OrganizationBundle\Migrations\Data\ORM\LoadOrganizationAndBusinessUnitData'];
    }

    /**
     * Assign exists sidebar widgets to the default organization
     */
    public function load(ObjectManager $manager)
    {
        $this->update($manager, Widget::class);
    }
}
