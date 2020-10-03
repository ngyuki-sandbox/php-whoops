# Whoops

## PSR-7/PSR-15

- https://packagist.org/packages/franzl/whoops-middleware
    - whoops を直接 new してるのでカスタマイズの余地がない
        - なので PrettyPageHandler で ApplicationPaths を設定する術もない
    - Accept ヘッダから使用するハンドラを自動で判別
        - ヘッダのパースは割と雑い
    - zend-diactoros へ依存していたりするのでまったくメンテされてなさそう
- https://packagist.org/packages/middlewares/whoops
    - register_shutdown_function もやるので例外にならないエラーも拾える
    - Accept ヘッダから使用するハンドラを自動で判別
        - ヘッダのパースは割と雑い
    - 素のままだと PrettyPageHandler で ApplicationPaths を設定しない
    - コンストラクタで Whoops のインスタンスを渡せるのでカスタマイズ可能
        - ただその場合はレスポンス形式の判断も自前でやる必要がある
        - ↓の方法でカスタマイズするほうが良さそう
    - WhoopsHandlerContainer を継承したインスタンスを渡せば Accept に応じた任意のハンドラを使える
        - psr-container だけど id に Accept ヘッダがそのまま渡るのでコンテナっぽくはない
        - 単にインタフェースだけ PSR にした、というだけのもよう
        - `whoops.text/html` みたいなのが渡るとかならまだわからなくもないのだけど・・

## WhoopsSmartyDataTable

Smarty 関係のデータを PrettyPageHandler で表示するためのデータテーブルのコールバック。

- Smarty テンプレートへアサインした値をデータテーブルに表示
- Smarty コンパイル済テンプレートに対応するテンプレート名を表示
- Smarty コンパイル済テンプレートを Application Stack に表示

例外のスタックトレースの `args` から `Smarty_Internal_Template` を探してきて処理してみました。
`zend.exception_ignore_args` が off じゃないとまとも機能しません。

## WhoopsEditorCallback

PrettyPageHandler の setEditor で PHP をリモート実行しているときでもエディタが開けるようにするためのもの。
リモートとローカルのパスのマッピングを簡単に登録できるようにしてみた

## PDO

Smarty と同じ方法で SQL 文やパラメータをデータテーブルに表示できるかと思ったけど、
prepare と execute が別メソッドなので同じスタックトレース上に現れないし、
PDOStatement がスタックトレースの args に入ることもないので無理だった。
