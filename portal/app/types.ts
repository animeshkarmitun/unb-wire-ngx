export interface WireMedia {
  id: number;
  public_id: string;
  caption: string;
  kind: string;
  credit?: string;
  dur?: string;
}

export interface WireStory {
  id: number | string;
  public_id: string;
  headline: string;
  sub_head?: string;
  brief: string;
  body_html?: string;
  category: string;
  language: string;
  published_at: string;
  status: string;
  is_breaking: boolean;
  tags: string[];
  caps: string[];
  media?: WireMedia[];
  has_video?: boolean;
  ex?: 'you' | 'other' | null;
  mins?: number;
}

export interface ClientContext {
  name: string;
  initials: string;
  tier: string;
  renews_at: string;
  stories_quota: number;
  stories_used: number;
  media_quota: number;
  media_used: number;
}

export interface SavedSearch {
  name: string;
  q: string;
}

export interface PortalContextData {
  client: ClientContext;
  saved_searches: SavedSearch[];
}

export interface MediaLibraryItem {
  id: string;
  src: 'unb' | 'ap';
  type: 'photo' | 'video';
  grad: string;
  cap: string;
  credit: string;
  by: string;
  date: string;
  d: number;
  loc: string;
  dim: string;
  ent: 'inc' | 'addon' | 'lock' | 'ex' | 'emb';
  cat: string;
}

export interface GalleryItem {
  id: string;
  title: string;
  n: number;
  grad: string;
  story: string;
  storyTitle: string;
  caps: string[];
}

export interface CollectionItem {
  id: string;
  name: string;
  n: number;
  grad: string;
  kw: string;
}

export interface TrendingItem {
  cap: string;
  dl: number;
}

export interface StreamPhotoItem {
  id: string;
  cap: string;
  by: string;
  loc: string;
  date: string;
  grad: string;
  story: string | null;
  kw: string;
}
