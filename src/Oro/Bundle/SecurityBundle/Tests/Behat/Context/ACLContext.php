<?php

namespace Oro\Bundle\SecurityBundle\Tests\Behat\Context;

use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Mink\Exception\ExpectationException;
use Behat\Symfony2Extension\Context\KernelAwareContext;
use Behat\Symfony2Extension\Context\KernelDictionary;
use Doctrine\Common\Inflector\Inflector;
use Oro\Bundle\DataGridBundle\Tests\Behat\Element\Grid;
use Oro\Bundle\DataGridBundle\Tests\Behat\Element\GridFilterStringItem;
use Oro\Bundle\NavigationBundle\Tests\Behat\Element\MainMenu;
use Oro\Bundle\TestFrameworkBundle\Behat\Context\OroFeatureContext;
use Oro\Bundle\TestFrameworkBundle\Behat\Element\OroPageObjectAware;
use Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\PageObjectDictionary;
use Oro\Bundle\UserBundle\Entity\Role;
use Oro\Bundle\UserBundle\Entity\User;
use Oro\Bundle\UserBundle\Tests\Behat\Element\UserRoleForm;
use Oro\Bundle\TestFrameworkBundle\Tests\Behat\Context\OroMainContext;

class ACLContext extends OroFeatureContext implements OroPageObjectAware, KernelAwareContext
{
    use PageObjectDictionary, KernelDictionary;

    /**
     * @var OroMainContext
     */
    private $oroMainContext;

    /**
     * @BeforeScenario
     */
    public function gatherContexts(BeforeScenarioScope $scope)
    {
        $environment = $scope->getEnvironment();
        $this->oroMainContext = $environment->getContext(OroMainContext::class);
    }

    /**
     * This is perform the change the organization from dashboard
     * It's need to be authenticated before perform this step
     * Example: Given I login as administrator
     *          And I am logged in under System organization
     *
     * @Given /^(?:|I am )logged in under (?P<organization>(\D*)) organization$/
     */
    public function iAmLoggedInUnderSystemOrganization($organization)
    {
        $page = $this->getSession()->getPage();
        $page->find('css', '.btn-organization-switcher')->click();
        $page->find('css', '.dropdown-organization-switcher')->clickLink($organization);
    }

    //@codingStandardsIgnoreStart
    /**
     * Set access level for action for specified entity for role
     * Two roles is supported - User and Administrator
     * Example: Given user permissions on Create Account is set to None
     * Example: And user have "User" permissions for "View" "Business Customer" entity
     * Example: And I set administrator permissions on Delete Cases to None
     *
     * @Given /^(?P<user>(administrator|user)) have "(?P<accessLevel>(?:[^"]|\\")*)" permissions for "(?P<action>(?:[^"]|\\")*)" "(?P<entity>(?:[^"]|\\")*)" entit(y|ies)$/
     * @Given /^(?P<user>(administrator|user)) permissions on (?P<action>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) is set to (?P<accessLevel>(?:[^"]|\\")*)$/
     * @When /^(?:|I )set (?P<user>(administrator|user)) permissions on (?P<action>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) to (?P<accessLevel>(?:[^"]|\\")*)$/
     */
    //@codingStandardsIgnoreEnd
    public function iHavePermissionsForEntity($entity, $action, $accessLevel, $user)
    {
        $role = $this->getRole($user);
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1080, 'current');

        $singularizedEntities = array_map(function ($element) {
            return trim(ucfirst(Inflector::singularize($element)));
        }, explode(',', $entity));

        $this->loginAsAdmin();

        $userRoleForm = $this->openRoleEditForm($role);

        foreach ($singularizedEntities as $singularizedEntity) {
            $userRoleForm->setPermission($singularizedEntity, $action, $accessLevel);
        }

        $userRoleForm->saveAndClose();
        $this->waitForAjax();

        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    //@codingStandardsIgnoreStart
    /**
     * Set access level for several actions for specified entity for role
     * Two roles is supported - User and Administrator
     *
     * Example: Given user permissions on View Accounts as User and on Delete as System
     * Example: Given administrator permissions on View Cases as System and on Delete as User
     *
     * @Given /^(?P<user>(administrator|user)) permissions on (?P<action1>(?:|View|Create|Edit|Delete|Assign|Share)) (?P<entity>(?:[^"]|\\")*) as (?P<accessLevel1>(?:[^"]|\\")*) and on (?P<action2>(?:|View|Create|Edit|Delete|Assign|Share)) as (?P<accessLevel2>(?:[^"]|\\")*)$/
     */
    //@codingStandardsIgnoreEnd
    public function iHaveSeveralPermissionsForEntity($user, $entity, $action1, $accessLevel1, $action2, $accessLevel2)
    {
        $role = $this->getRole($user);
        $this->getMink()->setDefaultSessionName('second_session');
        $this->getSession()->resizeWindow(1920, 1080, 'current');

        $singularizedEntity = ucfirst(Inflector::singularize($entity));
        $this->loginAsAdmin();

        $userRoleForm = $this->openRoleEditForm($role);
        $userRoleForm->setPermission($singularizedEntity, $action1, $accessLevel1);
        $userRoleForm->setPermission($singularizedEntity, $action2, $accessLevel2);
        $userRoleForm->saveAndClose();
        $this->waitForAjax();

        $this->getSession('second_session')->stop();
        $this->getMink()->setDefaultSessionName('first_session');
    }

    /**
     * @Then /^(?:|I )click update schema$/
     */
    public function iClickUpdateSchema()
    {
        $page = $this->getPage();

        $page->clickLink('Update schema');
        $this->waitForAjax();
        $page->clickLink('Yes, Proceed');
        $this->waitForAjax(120000);
    }

    /**
     * Click edit entity button on entity view page
     * Example: Given I'm edit entity
     *
     * @Given /^(?:|I |I'm )edit entity$/
     */
    public function iMEditEntity()
    {
        $this->createElement('Entity Edit Button')->click();
    }

    protected function loginAsAdmin()
    {
        $this->oroMainContext->loginAsUserWithPassword();
        $this->waitForAjax();
    }

    /**
     * @param $role
     * @return UserRoleForm
     */
    protected function openRoleEditForm($role)
    {
        /** @var MainMenu $mainMenu */
        $mainMenu = $this->createElement('MainMenu');
        $mainMenu->openAndClick('System/ User Management/ Roles');
        $this->waitForAjax();

        $this->createElement('GridFilersButton')->open();

        /** @var GridFilterStringItem $filterItem */
        $filterItem = $this->createElement('GridFilters')->getFilterItem('GridFilterStringItem', 'Label');

        $filterItem->open();
        $filterItem->selectType('is equal to');
        $filterItem->setFilterValue($role);
        $filterItem->submit();
        $this->waitForAjax();

        /** @var Grid $grid */
        $grid = $this->createElement('Grid');

        $grid->clickActionLink($role, 'Edit');
        $this->waitForAjax();

        return $this->elementFactory->createElement('UserRoleForm');
    }

    /**
     * @param string $user
     * @return Role
     * @throws ExpectationException
     */
    protected function getRole($user)
    {
        if ('administrator' === $user) {
            return $this->getContainer()
                ->get('oro_entity.doctrine_helper')
                ->getEntityRepositoryForClass(Role::class)
                ->findOneBy(['role' => User::ROLE_ADMINISTRATOR]);
        } elseif ('user' === $user) {
            return $this->getContainer()
                ->get('oro_entity.doctrine_helper')
                ->getEntityRepositoryForClass(Role::class)
                ->findOneBy(['role' => User::ROLE_DEFAULT]);
        }

        throw new ExpectationException(
            "Unexpected user '$user' for role permission changes",
            $this->getSession()->getDriver()
        );
    }
}
