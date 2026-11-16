<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        // 公開APIで存在しない書籍IDが指定された場合は、日本語の404 JSONを返す
        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*') && $e->getPrevious() instanceof ModelNotFoundException) {
                return response()->json(['message' => '書籍が見つかりません。'], 404);
            }
        });

        // 公開APIで権限のない操作（他人の書籍の更新・削除）をした場合は、日本語の403 JSONを返す
        $this->renderable(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json(['message' => 'この操作を行う権限がありません。'], 403);
            }
        });
    }

    /**
     * 公開API（api/*）へのリクエストには、Accept ヘッダーの有無にかかわらずJSONでエラーを返す。
     * これがないと、バリデーションエラーがリダイレクト（302）になってしまう。
     */
    protected function shouldReturnJson($request, Throwable $e): bool
    {
        return $request->is('api/*') || parent::shouldReturnJson($request, $e);
    }

    /**
     * 未認証のときの応答。公開API（api/*）には日本語の401 JSONを返し、
     * Web画面はこれまでどおりログイン画面にリダイレクトする。
     *
     * @param  Request  $request  未認証でアクセスしたリクエスト
     * @param  AuthenticationException  $exception  認証エラー
     * @return Response 401 のJSON、またはログイン画面へのリダイレクト
     */
    protected function unauthenticated($request, AuthenticationException $exception): Response
    {
        if ($request->is('api/*')) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        return parent::unauthenticated($request, $exception);
    }
}
