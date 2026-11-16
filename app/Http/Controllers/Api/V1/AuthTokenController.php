<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;

class AuthTokenController extends Controller
{
    /**
     * AP06: ログインAPI。メールアドレスとパスワードを確認し、APIトークンを発行する。
     * 発行したトークンは、書き込み系APIで Authorization: Bearer {token} として送る。
     *
     * @param  LoginRequest  $request  バリデーション済みの email / password
     * @return JsonResponse トークンとユーザー情報（200）。認証情報が誤っている場合は 401
     */
    public function store(LoginRequest $request): JsonResponse
    {
        $user = User::where('email', $request->validated('email'))->first();

        // メールアドレスとパスワードのどちらが誤っているかは、伝えない（登録の有無を推測されないようにするため）
        if ($user === null || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json(['message' => '認証が必要です。'], 401);
        }

        // plainTextToken は発行時にしか取得できない（DBにはハッシュ化した値だけが保存される）
        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user->only(['id', 'name', 'email']),
        ]);
    }
}
