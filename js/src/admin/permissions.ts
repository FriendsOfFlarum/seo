import { PermissionType } from 'flarum/admin/components/PermissionGrid';

// Custom permission category not in core's PermissionType union; the
// permissionItems() extender renders it as its own section.
export const SEO_PERMISSION_CATEGORY = 'seo' as unknown as PermissionType;
