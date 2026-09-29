<?php

declare(strict_types=1);

namespace Woit\T3ContentQuality\Security;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Central page/record permission checks for the extension's backend entry
 * points. Mirrors what core modules check before showing or editing a page.
 */
class PermissionService
{
    /** Page is inside the user's web mounts and readable. */
    public function canShowPage(int $pageUid, int $languageUid = 0): bool
    {
        return $pageUid > 0
            && $this->getPageRecordIfAllowed($pageUid, Permission::PAGE_SHOW) !== null
            && $this->canAccessLanguage($languageUid);
    }

    /** Page properties (title, description) may be edited by the user. */
    public function canEditPage(int $pageUid, int $languageUid = 0): bool
    {
        $backendUser = $this->getBackendUser();
        return $backendUser !== null
            && $this->canShowPage($pageUid, $languageUid)
            && $this->getPageRecordIfAllowed($pageUid, Permission::PAGE_EDIT) !== null
            && $backendUser->check('tables_modify', 'pages');
    }

    /** Content (and file references of content) on the page may be edited by the user. */
    public function canEditPageContent(int $pageUid, int $languageUid = 0): bool
    {
        $backendUser = $this->getBackendUser();
        return $backendUser !== null
            && $this->canShowPage($pageUid, $languageUid)
            && $this->getPageRecordIfAllowed($pageUid, Permission::CONTENT_EDIT) !== null
            && $backendUser->check('tables_modify', 'sys_file_reference');
    }

    /** Checks the edit permission required for a given pending-fix type. */
    public function canApplyFixType(string $fixType, int $pageUid, int $languageUid = 0): bool
    {
        return $fixType === 'alt_text'
            ? $this->canEditPageContent($pageUid, $languageUid)
            : $this->canEditPage($pageUid, $languageUid);
    }

    /**
     * @param int[] $pageUids
     * @return int[]
     */
    public function filterShowablePageUids(array $pageUids): array
    {
        return array_values(array_filter(
            array_map('intval', $pageUids),
            fn(int $pageUid): bool => $this->canShowPage($pageUid)
        ));
    }

    private function getPageRecordIfAllowed(int $pageUid, int $permission): ?array
    {
        $backendUser = $this->getBackendUser();
        if ($backendUser === null) {
            return null;
        }
        $page = BackendUtility::readPageAccess($pageUid, $backendUser->getPagePermsClause($permission));
        return is_array($page) && $page !== [] ? $page : null;
    }

    private function canAccessLanguage(int $languageUid): bool
    {
        $backendUser = $this->getBackendUser();
        return $backendUser !== null && ($backendUser->isAdmin() || $backendUser->checkLanguageAccess($languageUid));
    }

    private function getBackendUser(): ?BackendUserAuthentication
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        return $backendUser instanceof BackendUserAuthentication ? $backendUser : null;
    }
}
