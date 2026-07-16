<?php

namespace App\Http\Controllers\Admin\Traits;

use Illuminate\Support\Str;

/**
 * Enforces config/access.php page permissions on a Backpack CrudController.
 *
 * A controller opts in simply by adding `use ChecksAccess;`. The page key is
 * derived from the CRUD route (its last segment, e.g. ".../warehouse-expense"
 * => "warehouse-expense"), matching the keys in config/access.php.
 *
 * For each standard operation the page supports, the current user must hold the
 * "{page}.{operation}" permission; otherwise that operation is denied — Backpack
 * returns 403 on the route and hides the corresponding button. Pages absent from
 * the config are left unmanaged (no restriction added).
 *
 * Administrators bypass every check via the Gate::before() rule, so they are
 * never denied here. Override $accessPage on a controller if its route slug does
 * not match its config key.
 */
trait ChecksAccess
{
    /**
     * Backpack operations that map 1:1 to a page permission action.
     */
    protected array $accessControlledOperations = ['list', 'create', 'update', 'delete', 'show'];

    /**
     * Runs after setup(); the ideal moment to lock down operations.
     */
    protected function setupConfigurationForCurrentOperation()
    {
        parent::setupConfigurationForCurrentOperation();

        $this->applyAccessControl($this->accessPage());
    }

    /**
     * Resolve the config/access.php page key for this controller.
     */
    protected function accessPage(): ?string
    {
        if (property_exists($this, 'accessPage') && $this->accessPage) {
            return $this->accessPage;
        }

        $route = $this->crud->getRoute();

        return $route ? Str::afterLast(trim($route, '/'), '/') : null;
    }

    protected function applyAccessControl(?string $page): void
    {
        if (! $page) {
            return;
        }

        $actions = config("access.pages.{$page}.actions");

        if (! $actions) {
            return; // page not managed by the access system
        }

        $user = backpack_user();

        foreach ($this->accessControlledOperations as $operation) {
            if (! in_array($operation, $actions, true)) {
                continue; // this page does not expose that operation
            }

            if (! $user || ! $user->can("{$page}.{$operation}")) {
                $this->crud->denyAccess($operation);
            }
        }
    }
}
