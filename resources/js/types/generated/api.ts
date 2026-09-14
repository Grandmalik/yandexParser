export type ApiErrorCode = 'validation.failed' | 'auth.unauthenticated' | 'auth.forbidden' | 'auth.session_expired' | 'resource.not_found' | 'http.method_not_allowed' | 'http.rate_limited' | 'http.error' | 'internal';
export type ApiErrorData = {
code: string,
message: string,
fields: Record<string, string[]> | null,
details: Record<string, any> | null,
trace_id: string | null,
};
export type ApplicationConfigData = {
name: string,
locale: string,
};
export type BroadcastingConfigData = {
enabled: boolean,
driver: string,
key: string | null,
host: string | null,
port: number | null,
scheme: string | null,
auth_endpoint: string,
};
export type ConnectOrganizationData = {
url: string,
};
export type CursorPaginatedDataCollection<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type CursorPaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
path: string,
per_page: number,
next_cursor: string | null,
next_page_url: string | null,
prev_cursor: string | null,
prev_page_url: string | null,
},
};
export type CursorPaginatorInterface<TKey, TValue> = CursorPaginator<TKey, TValue>;
export type DriftStage = 'organization_page' | 'token' | 'signature' | 'reviews_request' | 'reviews_page';
export type EnumOptionData = {
value: string,
label: string,
};
export type IdentityErrorCode = 'auth.invalid_credentials';
export type LengthAwarePaginator<TKey, TValue> = {
data: TKey extends string ? Record<TKey, TValue> : TValue[],
links: {
url: string | null,
label: string,
active: boolean,
}[],
meta: {
total: number,
current_page: number,
first_page_url: string,
from: number | null,
last_page: number,
last_page_url: string,
next_page_url: string | null,
path: string,
per_page: number,
prev_page_url: string | null,
to: number | null,
},
};
export type LengthAwarePaginatorInterface<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type LoginData = {
email: string,
password: string,
remember: boolean,
};
export type MetricsSnapshotData = {
captured_at: string,
rating: number,
ratings_count: number,
reviews_count: number,
rating_change: number | null,
ratings_count_change: number | null,
reviews_count_change: number | null,
};
export type OrganizationData = {
id: string,
platform: EnumOptionData,
external_id: string,
source_url: string,
name: string | null,
address: string | null,
rating_summary: RatingSummaryData | null,
metrics_updated_at: string | null,
reviews_stored: number,
channel: string,
};
export type PaginatedDataCollection<TKey, TValue> = LengthAwarePaginator<TKey, TValue>;
export type Platform = 'yandex';
export type PlatformHealthData = {
platform: EnumOptionData,
status: EnumOptionData,
checks: number,
failures: number,
failure_share: number,
last_error: string | null,
paused_for_seconds: number | null,
};
export type RatingSummaryData = {
rating: number,
ratings_count: number,
reviews_count: number,
};
export type ReviewData = {
id: number,
author_name: string | null,
published_at: string,
text: string | null,
rating: number | null,
business_reply: string | null,
};
export type ScrapingErrorCode = 'source.invalid_url' | 'source.unsupported_host' | 'source.not_an_organization' | 'source.not_found' | 'source.unavailable' | 'source.rate_limited' | 'source.blocked' | 'source.drift' | 'source.partial';
export type SourceStatus = 'healthy' | 'degraded' | 'paused';
export type SyncConfigData = {
progress_event: string,
polling_interval_ms: number,
statuses: EnumOptionData[],
};
export type SyncErrorCode = 'sync.already_running' | 'sync.internal';
export type SyncFailureData = {
code: string,
message: string,
};
export type SyncProgressData = {
current: number,
total: number | null,
percent: number | null,
};
export type SyncRunData = {
id: string,
organization_id: string,
status: EnumOptionData,
is_finished: boolean,
trigger: string,
progress: SyncProgressData,
attempts: number,
error: SyncFailureData | null,
stats: SyncStatsData | null,
requested_at: string,
started_at: string | null,
finished_at: string | null,
};
export type SyncStatsData = {
created: number,
updated: number,
unchanged: number,
removed: number,
};
export type SyncStatus = 'queued' | 'running' | 'completed' | 'partial' | 'failed';
export type SyncTrigger = 'connected' | 'manual' | 'scheduled';
export type UserData = {
id: number,
name: string,
email: string,
};
