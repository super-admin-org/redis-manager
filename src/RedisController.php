<?php

namespace SuperAdmin\Admin\RedisManager;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;
use SuperAdmin\Admin\Facades\Admin;
use SuperAdmin\Admin\Layout\Content;
use Throwable;

class RedisController extends BaseController
{
    /**
     * Key being edited.
     *
     * @var array
     */
    protected $data = [];

    /**
     * Keys of the selected connection, one SCAN page at a time.
     */
    public function index(Request $request)
    {
        return Admin::content(function (Content $content) use ($request) {
            $manager = $this->manager();

            $content->header('Redis manager');
            $content->description('Connections');
            $content->breadcrumb(['text' => 'Redis manager']);

            try {
                $vars = $manager->vars();

                $page = $manager->scan(
                    (string) $request->input('pattern', '*'),
                    (int) $request->input('count', static::pageSize()),
                    (string) $request->input('cursor', '0')
                );
            } catch (Throwable $e) {
                $content->body($this->connectionError($manager, $e));

                return;
            }

            $vars['keys'] = $page['keys'];
            $vars['nextCursor'] = $page['cursor'];
            $vars['pattern'] = (string) $request->input('pattern', '*');
            $vars['count'] = (int) $request->input('count', static::pageSize());

            $content->body(view('super-admin-redis-manager::index', $vars));
        });
    }

    /**
     * A readable message instead of an error page when Redis cannot be reached.
     */
    protected function connectionError(RedisManager $manager, Throwable $e): string
    {
        $hint = str_contains($e->getMessage(), 'Class "Redis" not found')
            ? 'The connection uses the phpredis client, but the PHP redis extension is not installed. Install it, or set REDIS_CLIENT=predis and run composer require predis/predis.'
            : 'Check that Redis is running and that REDIS_HOST, REDIS_PORT and REDIS_PASSWORD are correct.';

        return sprintf(
            '<div class="alert alert-warning"><strong>Cannot connect to Redis connection <code>%s</code>.</strong><p class="mb-1 mt-2">%s</p><p class="mb-0 small text-muted">%s</p></div>',
            e($manager->connectionName()),
            e($hint),
            e($e->getMessage())
        );
    }

    public function edit(Request $request)
    {
        $manager = $this->manager();
        $this->data = $manager->fetch($request->input('key'));

        if (empty($this->data)) {
            admin_toastr('The key does not exist (it may have expired).', 'warning');

            return redirect(route('redis-index', ['conn' => $manager->connectionName()]));
        }

        return Admin::content(function (Content $content) use ($request, $manager) {
            $vars = $manager->vars();
            $vars['form'] = $this->form($request);
            $vars['form_title'] = __('Edit');

            $content->header('Redis manager');
            $content->description('Connections');
            $content->breadcrumb(
                ['text' => 'Redis manager', 'url' => route('redis-index', ['conn' => $vars['conn']])],
                ['text' => 'Edit']
            );
            $content->body(view('super-admin-redis-manager::form', $vars));
        });
    }

    public function create(Request $request)
    {
        if (RedisManager::readOnly()) {
            return $this->readOnlyResponse();
        }

        return Admin::content(function (Content $content) use ($request) {
            $manager = $this->manager();
            $vars = $manager->vars();
            $vars['form'] = $this->form($request);
            $vars['form_title'] = __('Create');

            $content->header('Redis manager');
            $content->description('Connections');
            $content->breadcrumb(
                ['text' => 'Redis manager', 'url' => route('redis-index', ['conn' => $vars['conn']])],
                ['text' => 'Create']
            );
            $content->body(view('super-admin-redis-manager::form', $vars));
        });
    }

    public function form(Request $request)
    {
        $type = !empty($this->data) ? $this->data['type'] : $request->input('type', 'string');

        $dataType = $this->manager()->dataType($type);

        if (!empty($this->data)) {
            $dataType->setData($this->data);
        }

        return $dataType->getForm();
    }

    public function store(Request $request)
    {
        if (RedisManager::readOnly()) {
            return $this->readOnlyResponse();
        }

        $response = $this->manager()->dataType($request->input('type'))->store($request->all());

        admin_toastr('Saved', 'success');

        return $response;
    }

    public function destroy(Request $request)
    {
        try {
            return $this->manager()->del($request->input('key'));
        } catch (Throwable $e) {
            return response()->json(['status' => false, 'message' => $e->getMessage()], 403);
        }
    }

    public function fetch(Request $request)
    {
        return $this->manager()->fetch($request->input('key'));
    }

    public function remove(Request $request)
    {
        if (RedisManager::readOnly()) {
            return response()->json(['status' => false, 'message' => 'The Redis manager is read-only.'], 403);
        }

        return $this->manager()->dataType($request->input('type'))->remove($request->all());
    }

    public function update(Request $request)
    {
        if (RedisManager::readOnly()) {
            return $this->readOnlyResponse();
        }

        $response = $this->manager()->update($request);

        admin_toastr('Saved', 'success');

        return $response;
    }

    public function console(Request $request)
    {
        return Admin::content(function (Content $content) {
            $manager = $this->manager();
            $vars = $manager->vars();

            $content->header('Redis manager');
            $content->description('Connections');
            $content->breadcrumb(
                ['text' => 'Redis manager', 'url' => route('redis-index', ['conn' => $vars['conn']])],
                ['text' => 'Console']
            );
            $content->body(view('super-admin-redis-manager::console', $vars));
        });
    }

    public function execute(Request $request)
    {
        try {
            $result = $this->manager()->execute($request->input('command'));
        } catch (Throwable $exception) {
            return $this->renderException($exception);
        }

        if (is_string($result) && Str::startsWith($result, ['ERR ', 'WRONGTYPE '])) {
            return $this->renderException(new \RuntimeException($result));
        }

        return $this->getDumpedHtml($result);
    }

    public static function pageSize(): int
    {
        return (int) RedisManager::config('scan_count', 50);
    }

    protected function renderException(Throwable $exception)
    {
        return sprintf(
            "<div class='callout callout-warning'><i class='icon-exclamation-triangle'></i>&nbsp;&nbsp;%s</div>",
            nl2br(e($exception->getMessage()))
        );
    }

    protected function getDumpedHtml($var)
    {
        ob_start();
        dump($var);
        $content = (string) ob_get_clean();

        $start = strpos($content, '<pre ');

        return $start === false ? '<pre>'.e(trim(strip_tags($content))).'</pre>' : substr($content, $start);
    }

    protected function readOnlyResponse()
    {
        admin_toastr('The Redis manager is read-only.', 'error');

        return redirect(route('redis-index', ['conn' => $this->manager()->connectionName()]));
    }

    protected function manager(): RedisManager
    {
        return RedisManager::instance();
    }
}
