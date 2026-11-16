<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ISBN検索（GET /books/isbn/{isbn}）のバリデーション。
 * 画面のJavaScriptから呼ばれるため、エラーは {"error": "メッセージ"} のJSONで返す。
 */
class FetchBookByIsbnRequest extends FormRequest
{
    /**
     * ISBN検索はログイン済みなら誰でもできる（ログインの確認は auth ミドルウェアで行う）。
     *
     * @return bool 許可する場合は true（false のときは403になる）
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * URLに含まれる ISBN を、バリデーションの対象に加える。
     * （FormRequest はフォームやクエリの値だけを検証するため、URLの値は自分で渡す必要がある）
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['isbn' => $this->route('isbn')]);
    }

    /**
     * ISBN検索のバリデーションルール。
     *
     * @return array<string, array<int, mixed>> 「項目名 => ルールの配列」の形式
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
        ];
    }

    /**
     * バリデーションエラー時の日本語メッセージ。
     *
     * @return array<string, string> 「項目名.ルール名 => メッセージ」の形式
     */
    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは13桁の数字で入力してください。',
            'isbn.digits' => 'ISBNは13桁の数字で入力してください。',
        ];
    }

    /**
     * バリデーション失敗時は、422 と {"error": "メッセージ"} を返す。
     *
     * @param  Validator  $validator  失敗したバリデーター
     *
     * @throws HttpResponseException
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['error' => $validator->errors()->first()], 422)
        );
    }
}
