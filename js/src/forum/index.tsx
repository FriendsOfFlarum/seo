import app from 'flarum/forum/app';
import DiscussionControls from 'flarum/forum/utils/DiscussionControls';
import Button from 'flarum/common/components/Button';
import { extend } from 'flarum/common/extend';
import MetaSeoModal from '../common/Components/MetaSeoModal';

export { default as extend } from './extend';

export * from '../common/extend';

app.initializers.add('fof-seo', () => {
  extend(DiscussionControls, 'moderationControls', function (items, discussion) {
    if (!app.forum.attribute('canConfigureSeo')) return;

    items.add(
      'manageSeo',
      <Button
        icon="fas fa-search"
        onclick={() =>
          app.modal.show(MetaSeoModal, {
            objectType: 'discussions',
            objectId: discussion.id(),
          })
        }
      >
        {app.translator.trans('fof-seo.forum.controls.configure_seo')}
      </Button>,
      -1000
    );
  });
});
