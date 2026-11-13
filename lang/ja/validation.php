<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | バリデーションエラーのメッセージ。主要な文言の形式は次のとおり。
    |   required: {項目}は必須です。
    |   max     : {項目}は{n}文字以内で入力してください。
    |   min     : {項目}は{n}文字以上で入力してください。
    |   unique  : この{項目}は既に登録されています。
    |   email   : {項目}はメール形式で入力してください。
    |
    */

    'accepted' => ':attributeに同意してください。',
    'accepted_if' => ':otherが:valueの場合、:attributeに同意してください。',
    'active_url' => ':attributeは正しいURLではありません。',
    'after' => ':attributeは:dateより後の日付を指定してください。',
    'after_or_equal' => ':attributeは:date以降の日付を指定してください。',
    'alpha' => ':attributeは英字のみで入力してください。',
    'alpha_dash' => ':attributeは英数字、ハイフン、アンダースコアのみで入力してください。',
    'alpha_num' => ':attributeは英数字のみで入力してください。',
    'array' => ':attributeは配列で指定してください。',
    'ascii' => ':attributeは半角の英数字と記号のみで入力してください。',
    'before' => ':attributeは:dateより前の日付を指定してください。',
    'before_or_equal' => ':attributeは:date以前の日付を指定してください。',
    'between' => [
        'array' => ':attributeは:min個から:max個の間で指定してください。',
        'file' => ':attributeは:min〜:maxキロバイトのファイルを指定してください。',
        'numeric' => ':attributeは:min〜:maxの範囲で入力してください。',
        'string' => ':attributeは:min〜:max文字で入力してください。',
    ],
    'boolean' => ':attributeはtrueまたはfalseを指定してください。',
    'can' => ':attributeに許可されていない値が含まれています。',
    'confirmed' => ':attributeが一致しません。',
    'current_password' => 'パスワードが正しくありません。',
    'date' => ':attributeは正しい日付で入力してください。',
    'date_equals' => ':attributeは:dateと同じ日付を指定してください。',
    'date_format' => ':attributeは:format形式で入力してください。',
    'decimal' => ':attributeは小数点以下:decimal桁で入力してください。',
    'declined' => ':attributeを拒否してください。',
    'declined_if' => ':otherが:valueの場合、:attributeを拒否してください。',
    'different' => ':attributeと:otherには異なる値を指定してください。',
    'digits' => ':attributeは:digits桁の数字で入力してください。',
    'digits_between' => ':attributeは:min〜:max桁の数字で入力してください。',
    'dimensions' => ':attributeの画像サイズが正しくありません。',
    'distinct' => ':attributeの値が重複しています。',
    'doesnt_end_with' => ':attributeは次のいずれかで終わらない値を指定してください。:values',
    'doesnt_start_with' => ':attributeは次のいずれかで始まらない値を指定してください。:values',
    'email' => ':attributeはメール形式で入力してください。',
    'ends_with' => ':attributeは次のいずれかで終わる値を指定してください。:values',
    'enum' => '選択された:attributeは正しくありません。',
    'exists' => '選択された:attributeは正しくありません。',
    'file' => ':attributeはファイルを指定してください。',
    'filled' => ':attributeを入力してください。',
    'gt' => [
        'array' => ':attributeは:value個より多く指定してください。',
        'file' => ':attributeは:valueキロバイトより大きいファイルを指定してください。',
        'numeric' => ':attributeは:valueより大きい値を入力してください。',
        'string' => ':attributeは:value文字より多く入力してください。',
    ],
    'gte' => [
        'array' => ':attributeは:value個以上指定してください。',
        'file' => ':attributeは:valueキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeは:value以上の値を入力してください。',
        'string' => ':attributeは:value文字以上で入力してください。',
    ],
    'image' => ':attributeは画像ファイルを指定してください。',
    'in' => '選択された:attributeは正しくありません。',
    'in_array' => ':attributeは:otherに含まれていません。',
    'integer' => ':attributeは整数で入力してください。',
    'ip' => ':attributeは正しいIPアドレスで入力してください。',
    'ipv4' => ':attributeは正しいIPv4アドレスで入力してください。',
    'ipv6' => ':attributeは正しいIPv6アドレスで入力してください。',
    'json' => ':attributeは正しいJSON文字列で入力してください。',
    'lowercase' => ':attributeは小文字で入力してください。',
    'lt' => [
        'array' => ':attributeは:value個より少なく指定してください。',
        'file' => ':attributeは:valueキロバイトより小さいファイルを指定してください。',
        'numeric' => ':attributeは:valueより小さい値を入力してください。',
        'string' => ':attributeは:value文字より少なく入力してください。',
    ],
    'lte' => [
        'array' => ':attributeは:value個以下で指定してください。',
        'file' => ':attributeは:valueキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeは:value以下の値を入力してください。',
        'string' => ':attributeは:value文字以内で入力してください。',
    ],
    'mac_address' => ':attributeは正しいMACアドレスで入力してください。',
    'max' => [
        'array' => ':attributeは:max個以下で指定してください。',
        'file' => ':attributeは:maxキロバイト以下のファイルを指定してください。',
        'numeric' => ':attributeは:max以下の値を入力してください。',
        'string' => ':attributeは:max文字以内で入力してください。',
    ],
    'max_digits' => ':attributeは:max桁以内の数字で入力してください。',
    'mimes' => ':attributeは:valuesタイプのファイルを指定してください。',
    'mimetypes' => ':attributeは:valuesタイプのファイルを指定してください。',
    'min' => [
        'array' => ':attributeは:min個以上選択してください。',
        'file' => ':attributeは:minキロバイト以上のファイルを指定してください。',
        'numeric' => ':attributeは:min以上の値を入力してください。',
        'string' => ':attributeは:min文字以上で入力してください。',
    ],
    'min_digits' => ':attributeは:min桁以上の数字で入力してください。',
    'missing' => ':attributeを指定しないでください。',
    'missing_if' => ':otherが:valueの場合、:attributeを指定しないでください。',
    'missing_unless' => ':otherが:valueでない場合、:attributeを指定しないでください。',
    'missing_with' => ':valuesが指定されている場合、:attributeを指定しないでください。',
    'missing_with_all' => ':valuesがすべて指定されている場合、:attributeを指定しないでください。',
    'multiple_of' => ':attributeは:valueの倍数で入力してください。',
    'not_in' => '選択された:attributeは正しくありません。',
    'not_regex' => ':attributeの形式が正しくありません。',
    'numeric' => ':attributeは数値で入力してください。',
    'password' => [
        'letters' => ':attributeには英字を1文字以上含めてください。',
        'mixed' => ':attributeには大文字と小文字を1文字以上ずつ含めてください。',
        'numbers' => ':attributeには数字を1文字以上含めてください。',
        'symbols' => ':attributeには記号を1文字以上含めてください。',
        'uncompromised' => '指定された:attributeは情報漏えいが確認されています。別の:attributeを指定してください。',
    ],
    'present' => ':attributeを指定してください。',
    'prohibited' => ':attributeは指定できません。',
    'prohibited_if' => ':otherが:valueの場合、:attributeは指定できません。',
    'prohibited_unless' => ':otherが:valuesでない場合、:attributeは指定できません。',
    'prohibits' => ':attributeが指定されている場合、:otherは指定できません。',
    'regex' => ':attributeの形式が正しくありません。',
    'required' => ':attributeは必須です。',
    'required_array_keys' => ':attributeには次の項目を含めてください。:values',
    'required_if' => ':otherが:valueの場合、:attributeは必須です。',
    'required_if_accepted' => ':otherに同意する場合、:attributeは必須です。',
    'required_unless' => ':otherが:valuesでない場合、:attributeは必須です。',
    'required_with' => ':valuesが指定されている場合、:attributeは必須です。',
    'required_with_all' => ':valuesがすべて指定されている場合、:attributeは必須です。',
    'required_without' => ':valuesが指定されていない場合、:attributeは必須です。',
    'required_without_all' => ':valuesがすべて指定されていない場合、:attributeは必須です。',
    'same' => ':attributeと:otherが一致しません。',
    'size' => [
        'array' => ':attributeは:size個で指定してください。',
        'file' => ':attributeは:sizeキロバイトのファイルを指定してください。',
        'numeric' => ':attributeは:sizeを入力してください。',
        'string' => ':attributeは:size文字で入力してください。',
    ],
    'starts_with' => ':attributeは次のいずれかで始まる値を指定してください。:values',
    'string' => ':attributeは文字列で入力してください。',
    'timezone' => ':attributeは正しいタイムゾーンを指定してください。',
    'unique' => 'この:attributeは既に登録されています。',
    'uploaded' => ':attributeのアップロードに失敗しました。',
    'uppercase' => ':attributeは大文字で入力してください。',
    'url' => ':attributeはURL形式で入力してください。',
    'ulid' => ':attributeは正しいULIDで入力してください。',
    'uuid' => ':attributeは正しいUUIDで入力してください。',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | 特定の項目・ルールだけ文言を変えたい場合は、ここに追加する。
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'custom-message',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | 項目名を日本語の表示名に置き換える。画面ごとに別名にしたい項目
    |（例: ジャンルの name は「ジャンル名」）は、FormRequest の attributes() で上書きする。
    |
    */

    'attributes' => [
        'name' => 'お名前',
        'email' => 'メールアドレス',
        'password' => 'パスワード',
        'password_confirmation' => 'パスワード（確認用）',
        'title' => '書名',
        'author' => '著者',
        'isbn' => 'ISBN',
        'published_date' => '出版日',
        'description' => '説明',
        'image_url' => '画像URL',
        'genres' => 'ジャンル',
        'rating' => '評価',
        'comment' => 'コメント',
    ],

];
