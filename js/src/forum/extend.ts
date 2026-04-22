import Extend from 'flarum/common/extenders';
import Discussion from 'flarum/common/models/Discussion';
import SeoMeta from '../common/Models/SeoMeta';

export default [
  new Extend.Store() //
    .add('seoMeta', SeoMeta),

  new Extend.Model(Discussion) //
    .hasOne<SeoMeta>('seoMeta'),
];
