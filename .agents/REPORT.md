# Review of `codewiser/simple-files`

Inspection date: 2026-10-06. Suite: 40 tests, 125 assertions, green on PHP 8.1.34.
Every bug below was reproduced with a script (kept in `.agents/verify.php`) unless marked otherwise.

## Round 4: six more findings

Reported after a second pass, reproduced and then re-checked with sections 7 to 9 of `.agents/verify.php`.

| # | Decision | Change |
|---|---|---|
| 1 | Drop keys | `Collection::sort()` keeps the original keys, so a reordered file list carried `[1, 0]` and `json_encode()` turned it into an object (`{"1":…,"0":…}`) — the `"files": [...]` array the README documents broke in any API response holding two files of different age. `latest()` and `oldest()` now reindex with `values()`. Covered by `testSortedFileListsStillEncodeAsJsonArrays`. |
| 2 | Fix | `put()` joined the filename to the mount point unchecked. `put($x, '')` wrote a *file* at `model/1` itself, after which every write failed with `UnableToCreateDirectory` and `flush()` could not repair it; `put($x, 'sub/nested.txt')` created a file that `files()`, `delete()` and `flush()` never see, and the leftover directory kept the mount point alive forever. `ensureFilenameIsPlain()` now rejects an empty name, `.`, `..`, and any `/`, `\` or NUL byte before the disk is touched — Flysystem rewrites `\` to `/`, so a backslash nests just like a slash. It guards `put()`, `storeAs()`, `copy()` and `storeUrl()`; `store()` guards as well, because `basename('/a/b/..')` is `..`, which resolves outside the mount. Covered by `testStorageRejectsFilenamesOutsideTheMountPoint`. |
| 3 | Ignored | Int-backed bucket enums stay broken, in two places: `BucketArgument::cast()` raises `TypeError` for a non-numeric segment (a 500, because the controller catches `Exception` and `TypeError` is an `Error`), and `Pool::getBucket()` compares the string `name()` with `===` against the int `value`, so `getBucket(Level::one)` leaks `ItemNotFoundException`. Both remain reproducible in `verify.php`. |
| 4 | Warn after | `warnWhenReplacing()` ran before the write, and Laravel's `HandleExceptions::handleError()` turns every reported error into a thrown `ErrorException`, so in a real app the replacement never happened while the README promised it did. The write now happens first, `FileWasStored` is dispatched, and only then does the warning fire: the new file stands even where warnings are exceptions, and the message reads "has been overwritten". Covered by the `$replaced` assertions in `testStoringOverAnExistingFileTriggersAWarning`. |
| 5 | Fix | `if (! $content)` dropped `'0'` — and `''` — silently, so `put('0', 'count.txt')` returned `null` and stored nothing. `put()` now returns `null` only for `null` and `false`; `'0'` and `''` are written as the content they are. `store()` keeps "nothing to store" for `null`, `false`, `''` and `[]`, while `'0'` and `0` are taken literally (`Local file "0" does not exist.` instead of a silent `null`). Covered by `testPutKeepsContentThatLooksEmpty`. |
| 6 | Fix | Filenames were matched with `==`, and `'01' == '1'` is true, so a request for `1` could be served the file named `01` (and `1e3` matched `1000`). `StorageController`, `FileCollection::one()` and `filterByPath()` now compare strictly. Covered by `testFilesAreFoundByTheirExactName`. |

Alongside this round `File::type()` was added: `mimeType()` gives the full value (`image/png`), `type()` its top level (`image`), and `mime()` remains as a deprecated alias — documented in the README, printed by section 7 of `verify.php` and covered by `testFileTypeGivesTheTopLevelOnly`.

## Round 3: `Storage::resolve()` knows the bucket argument

| # | Decision | Change |
|---|---|---|
| 4 | Fixed on the caller side | `Storage::resolve()` reflects the first parameter of the owner's `storage()` before handing the bucket over. No parameter raises an `InvalidArgumentException` naming the model — PHP would drop the argument silently and the download would serve the default bucket. A backed enum is built with `tryFrom()`: the route hands over a string, which used to raise a `TypeError` the controller does not catch, because `TypeError` is an `Error` and not an `Exception` — a 500 instead of a readable failure. A `string`, `mixed` or untyped argument is passed through as it is, and a pure enum or an unrelated type is rejected with a message that names both. Covered by `testResolveConvertsTheBucketNameToTheEnumCase`, `testResolveRejectsAnUnknownBucketName`, `testResolveRejectsABucketTheOwnerDoesNotAccept`, `testResolveRejectsAPureEnumBucket` and `testResolvePassesTheBucketNameToAnUntypedStorage`, with `tests/NoParamPost.php` as the fixture that declares no argument. |

That reflection lived in two places — `Storage::bucketArgument()` and `AttachmentableObserver::enumBuckets()` — and now sits in one class: `BucketArgument` reads the owner signature once and answers both questions, `buckets()` for the cases the observer flushes and `cast()` for the value the controller passes on. Covered by `BucketArgumentTest`; the observer keeps `enumBuckets()` as a one-line delegate, since it is protected API that subclasses may override.

The contract itself still declares `storage()` without a bucket argument, and PHP does not allow fixing that in a patch release. Since 7.4 an implementation may only widen a parameter type, never narrow it, so declaring `storage($bucket = null)` in `Attachmentable` turns every `storage(Bucket $bucket = null)` into a fatal error at class load:

```
Fatal error: Declaration of C::storage(?Bucket $bucket = null) must be compatible with I::storage($bucket = null)
```

Verified on PHP 8.1: untyped and `mixed` stay compatible, `?string`, `null|string|\BackedEnum`, `Bucket` and a missing parameter all fail. Nothing changed through 8.5, and no RFC proposes to allow narrowing, since a caller typed against the interface must be able to pass every promised value. Moving the parameter into the contract is therefore a breaking change for the next major release, and it has a second cost: `BucketArgument` — which `AttachmentableObserver::enumBuckets()` now relies on — learns the bucket enum by reflecting that very parameter type, so implementations that must go untyped would leave the observer nothing to enumerate.

## Round 2: decisions and fixes

| # | Decision | Change |
|---|---|---|
| 1 | Full path | `FileWasStored` receives `$file->path()` again — the full path as the disk resolves it, so a listener can open the file without re-resolving the storage. The event documents all three properties. Covered by `ObserverTest::testStoredFileEventCarriesFullPath`, which asserts the file really exists at that path. |
| 2 | Fix | `Storage::resolve()` reports `$morph` instead of `$class`, and no longer goes through `__()`, so the message survives outside a booted application. Covered by `testResolveRequiresAMorphAlias`. |
| 3 | Morph map is mandatory | No `class_exists()` fallback. The exception now names the failing alias and points at `Relation::morphMap()` / `Model::enforceMorphMap()`, which keeps mount points short and readable. |
| 4 | Deferred | The contract still declares `storage()` without a bucket argument; the silent drop was closed on the caller side in round 3 above. |
| 5 | Fix | `Storage::store()` copies a `File` through `readStream()` + `writeStream()` instead of its local `path()`, so a cloud disk no longer yields an empty file. Covered by `testStoreCopiesAFileFromAnotherDisk`, whose stub disk returns `s3://…` from `path()`. |
| 6 | Fix | Remote URL upload is implemented: `storeUrl()` streams the URL into the mount, takes the filename from the URL path (host as a fallback) and requires `allow_url_fopen`; a 404 raises `InvalidArgumentException`. Verified against `https://example.com/`. The README claim is back. |
| 7 | Fails on purpose | `File::toArray()` still throws when the file is gone; the README documents that it reads live metadata. |
| 8 | Warning | `warnWhenReplacing()` raises `E_USER_WARNING` for every write that replaces an existing path — `store()`, `put()`, copies and downloads alike. It now fires *after* the write, see round 4 above. Covered by `testStoringOverAnExistingFileTriggersAWarning`, which also asserts a fresh name stays silent and that the new file is already in place when the warning arrives. |
| 9 | Flysystem separator | `MountPoint` builds paths with `'/'` through a `SEPARATOR` constant, the separator Flysystem itself uses. |
| 10 | No files for unsaved owners | `store()` and `put()` throw `LogicException` while `owner->getKey()` is null, so the shared `post/0` mount point is never created. The observer still ignores keyless owners. `testOwnerWithoutKeyIsNotFlushed` was replaced by `testUnsavedOwnerDoesNotPersistFiles` and `testObserverIgnoresAnUnsavedOwner`. |
| 11 | Fix | Lists are flattened and empty items dropped; `Singular` unwraps nested lists before storing; a missing local path raises this package's `InvalidArgumentException`; Symfony `File` and `UploadedFile` are accepted (client name kept and basename-sanitized); contract docblocks now describe what the code really takes. |
| 12 | Fix | Strict `in_array()` in `delete()`; the misleading comment was already corrected; the `Pool` docblock now reads `file?: array`, since an empty singular bucket serializes `[]`; `FileWasStored` lost its `broadcastOn()` boilerplate and the unused `InteractsWithSockets` trait. |

## Round 1: findings

## Bugs

### 1. `FileWasStored` carries a full, undocumented path
`src/Storage.php:171` dispatches `new FileWasStored($file->path(), ...)`, and `File::path()`
resolves to a full path (`/Users/…/storage/model/1/c.txt`), while the rest of the API —
`delete()`, serialization, the README — speaks in relative paths (`model/1/c.txt`).

Kept as a full path after review: a listener must be able to open the file without
re-resolving the storage. What was missing is the promise, so the event now documents all
three properties, and `testStoredFileEventCarriesFullPath` pins the behaviour.

### 2. `Storage::resolve()` reports the wrong variable
`src/Storage.php:30-33` interpolates `$class` into the message, but `$class` is always `null`
on that branch, so the caller sees `Unrecognized class name for ""` and never learns which
morph alias failed. Fix: `'model' => $morph`.

### 3. `Storage::resolve()` rejects models without a morph map
`Relation::getMorphedModel()` only reads `Relation::$morphMap`, so even the fully qualified
class name of a model that does not call `enforceMorphMap()`/`morphMap()` is rejected.
A `class_exists($morph)` fallback would let the controller work without a morph map.

Related: `__()` is used in a library path; outside a booted application it raises
`Target class [translator] does not exist` instead of the intended `InvalidArgumentException`
(this is why the reproduction above never reached the message).

### 4. The bucket argument is silently dropped
`Attachmentable::storage()` declares **no** parameters (`src/Attachmentable.php:13`), yet
`Storage::resolve()` (`src/Storage.php:38`) and `AttachmentableObserver` (`src/AttachmentableObserver.php:57`)
call `storage($bucket)`. PHP tolerates extra arguments on userland methods, so an
implementation like `PlainPost::storage()` swallows the bucket and returns the default
storage — reproduced: `PlainPost->storage('docs')` returns `name() === NULL` mounted at
`plain_post/0`. A download URL for `post/1/docs/x.png` would then serve (or fail to find)
the wrong file, with no error anywhere.

Fix (round 3): `Storage::resolve()` now reflects the parameter of the owner's
`storage()` and either converts the name to the declared backed enum or raises
an `InvalidArgumentException` that names the model, so a bucket is never dropped
silently and never reaches a typed argument as a string. The contract itself was
left alone: PHP allows implementations to widen a parameter type only, never to
narrow it, so declaring the argument in `Attachmentable` would be a fatal error
for every `storage(Bucket $bucket = null)` — see round 3 above.

### 5. Copying a `File` from a non-local disk writes an empty file
`Storage::store()` (`src/Storage.php:203-207`) turns a `File` into `$content->path()` and feeds
that string to `putFile()`. For a cloud disk `path()` returns an `s3://…`-style string, so
`file_exists()` is false, Symfony's `fopen()` fails, and **zero bytes are stored** together with
PHP warnings. Reproduced with an emulated cloud disk: `store($file)` produced an empty file.

Fix: copy through the source disk, e.g. `$this->disk->writeStream($target, $content->readStream())`.

### 6. Remote URL upload is documented but does not work
README promises uploads "from a remote url" when `allow_url_fopen` is enabled. Reproduced with
a live URL: `store('https://example.com/')` throws
`FileNotFoundException: The file "https://example.com/" does not exist`, because neither
`file_exists()` nor Symfony's `File` accepts remote paths. Either implement a stream download
or drop the claim (the README now says "a path to a local file").

### 7. `File::toArray()` throws when the file is gone
`src/File.php:169-180` reads `size()`, `checksum()` and `mimeType()` unguarded; a file deleted
behind the storage's back raises `League\Flysystem\UnableToRetrieveMetadata` and breaks every
`Pool`/API-resource serialization that touches it. Reproduced. Guard with `exists()` (or catch
and emit `null`) so serialization stays total.

### 8. Same-name uploads silently overwrite each other
`putFileAs()` replaces an existing target: storing `test.png` twice leaves one file (reproduced:
two `put()` calls with `file.txt` ended with a single file holding the second content). For a
multi-file storage this is quiet data loss; for `Singular` it is the wanted behaviour.
Consider a unique suffix (Laravel's own `store()` hashes names) or document the collision rule.

### 9. `MountPoint` builds paths with `DIRECTORY_SEPARATOR`
`src/MountPoint.php:11,16` produces `post\1\docs` on Windows, while Flysystem normalises to `/`.
Use `'/'` explicitly — mount points are storage paths, not OS paths.

### 10. Unsaved owners share the `post/0` mount point
`MountPoint` falls back to `0` for a missing key, so every unsaved model writes into the same
directory, `Storage::propagateNewFile()` deliberately skips the event when there is no key, and
`AttachmentableObserver` skips flushing when there is no key. The result is an orphaned `post/0`
directory that no lifecycle hook ever cleans. Prefer a throw, or a unique temporary mount.

### 11. Edge cases in `store()`/`Singular::store()`
* `Singular::store()` reduces an array with `current()`, so `store([[a, b]])` hands a
  `FileCollection` to the parent and then reads `$file->path` on a collection → undefined
  property warning (PHPUnit runs with `failOnWarning`).
* `Storage::store()` (`src/Storage.php:195-198`) maps recursively without flattening, so a
  nested collection stays nested in the result and `null`s from failed items are kept.
* `store('/missing/path.png')` throws Symfony's `FileNotFoundException` from deep inside the
  stack instead of an `InvalidArgumentException` of this package.
* The `StorageContract` docblock advertises `StreamInterface|resource` content, but the
  implementation only handles `UploadedFile`, existing local paths, `File` and arrays.

### 12. Small correctness nits
* `Storage::delete()` (`src/Storage.php:134`) uses a loose `in_array()`; use `strict: true`.
* `src/Storage.php:130` comment says "Sort files and delete matching keys with a single
  listing" — nothing sorts (comment corrected in this pass).
* `Pool::toArray()` documents `file: null|array` and the README said `null`, but an empty
  singular bucket serializes `[]` (`Singular::toArray()`), so consumers must handle both.
* `FileWasStored::broadcastOn()` is untouched Laravel boilerplate publishing to a literal
  `channel-name`; the event never broadcasts. Remove it or derive the channel from the owner.

## Improvements

* **Report swallowed failures.** `AttachmentableObserver::flush()` catches every `Throwable`
  twice and stays silent; a failing disk therefore leaves orphan files with no log line.
  `report($e)` (or a logger) keeps "deletion must not fail" while making failures visible.
* **Cache metadata while sorting.** `FileCollection::latest()/oldest()` calls `lastModified()`
  inside the comparison — O(n log n) stat calls, each one a round trip on S3. Read the
  timestamps once, then sort.
* **Make `Pool::getBucket()` fail helpfully.** It leaks `ItemNotFoundException` /
  `MultipleItemsFoundException` from `sole()`; a package exception naming the bucket (and a
  duplicate check in `addBucket()`) would be kinder.
* **Deprecation consistency.** `upload()` is `@deprecated` on the contract but not on the class,
  and the README still demonstrates `upload()` everywhere; `store()` is the intended name
  (`@deprecated` added to `Storage::upload()` in this pass).
* **Test gaps.** `Storage::resolve()` is covered since round 2, but nothing
  exercises `StorageController` itself (route interpretation, policy check),
  `File::toArray()` on a missing file, or a real cloud disk — exactly where the
  bugs above live.
* **`File::mime()`** — resolved in round 4: `File::type()` now carries the clear name,
  `mimeType()` gives the full value, and `mime()` stays as a deprecated alias.
* **`composer.json`** allows every future Laravel (`laravel/framework: >=10.0`); an upper bound
  would keep surprises out of CI.

## README

Rewritten for spelling and sentence flow; facts corrected while touching them:

* "isolated from each over" → "isolated from one another", plus the other typos listed below.
* Documented remote URL upload again — it is implemented now (bug 6), together
  with the unsaved-owner rule and the overwrite warning.
* The `file` entry of an empty singular bucket is `[]`, not `null` (bug 12).
* The inline `StorageController` sample drifted from the shipped class; the section now
  describes the real controller and how the route segments are interpreted.
* Added notes that `files()` returns the newest first and that `toArray()` reads live metadata.
* Added the `composer require` line.

Spelling fixes: each over → one another; formed from → is formed from; default to → defaults to;
url → URL; Every stored file represented → Every stored file is represented; as `Response` →
as a `Response`; will contain only one element maximum → holds at most one element; may have few
storages → may have several storages; then the owner is destroyed → when the owner is destroyed;
until `forceDelete` call → until `forceDelete` is called; can not → cannot; in api resource →
in an API resource; no file were uploaded → no file has been uploaded; only then published →
only when it is published; application needs → the application needs; suggest to use → suggest
using; that is looks so → which looks like this.
