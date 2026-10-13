import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import PermissionGrid from 'flarum/admin/components/PermissionGrid';
import { SEO_PERMISSION_CATEGORY } from '../permissions';

export default function extendPermissionGrid() {
  extend(PermissionGrid.prototype, 'permissionItems', function (items) {
    // The admin search calls permissionItems() on the prototype, where there are no attrs.
    const extensionId = (this.attrs as { extensionId?: string } | undefined)?.extensionId;

    items.add(
      'seo',
      {
        label: app.translator.trans('fof-seo.admin.permissions.category_label'),
        children: extensionId
          ? app.registry.getExtensionPermissions(extensionId, SEO_PERMISSION_CATEGORY).toArray()
          : app.registry.getAllPermissions(SEO_PERMISSION_CATEGORY).toArray(),
      },
      80
    );
  });
}
