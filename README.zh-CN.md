<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Command

`super-kernel/command` 注册类用它的 `#[Command]` 注解声明的 Symfony Console 命令，并且只在命令被使用时才通过容器创建它。

## 安装

```bash
composer require super-kernel/command
```

命令运行在 `super-kernel/framework` 的应用中，该应用需要容器以及它列出的其他包；`super-kernel/skeleton` 会安装所有这些包。命令从 `skernel build` 生成的元数据中发现，因此项目需要用 SKERNEL 构建。

## 提供的能力

- 用 `#[Command]` 在继承 Symfony `Command` 的类上声明的命令，带有名称、描述、别名和隐藏标志。
- 对命令的构造函数注入，因为命令由容器创建。
- 延迟命令：列出命令和建议名称时不会创建任何命令。
- 拒绝空名称、无效别名、重复名称以及不是命令的类。
- 在应用启动时安装到应用中的命令加载器。

它不会：

- 注册用 Symfony 的 `#[AsCommand]` 声明的命令；
- 把方法注册为命令；
- 创建应用或运行命令，这由 `super-kernel/framework` 和 Symfony Console 负责。

## 快速开始

声明一个命令。将它保存为 `src/Command/HelloCommand.php`：

```php
<?php

declare(strict_types=1);

namespace Src\Command;

use SuperKernel\Command\Attribute\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command(name: 'app:hello', description: 'Print a greeting.', aliases: ['hello'])]
final class HelloCommand extends SymfonyCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return self::SUCCESS;
    }
}
```

构建项目，然后用别名运行命令：

```bash
skernel build
php bin/bootstrap.php hello
```

它会打印 `Hello`。

## 公开接口

| 名称 | 类别 | 用途 |
| --- | --- | --- |
| `SuperKernel\Command\Attribute\Command` | 注解 | 以一个名称注册被注解的 Symfony 命令，可附带描述、别名和隐藏标志。 |
| `Symfony\Component\Console\CommandLoader\CommandLoaderInterface` | 接口 | 由容器提供；延迟加载已注册的命令。 |

## 需要了解的行为

- 只有类上的 `#[Command]` 才会注册命令；只带有 Symfony `#[AsCommand]` 的类不会被注册。
- 命令在 Symfony 为了运行它、显示它的帮助或补全它而初始化它时通过容器创建；创建失败在那时报告，运行以非零状态结束。
- 空名称、不是非空字符串列表的别名、被使用两次的名称或别名，或者没有继承 Symfony `Command` 的类，会在创建加载器时抛出 `InvalidArgumentException`，使 `skernel build` 和应用启动失败。重复的情况会指出两个类：`Command name "<name>" is declared by both <class> and <class>.`
- 命令从构建元数据中发现：添加、删除或修改命令之后，请重新运行 `skernel build`，或者用会先构建的 `skernel run` 启动。

## 文档

开发者文档介绍了声明规则、向命令注入服务、内置命令和工具命令，以及每条错误消息：[SuperKernel Command](https://github.com/super-kernel/.github/blob/main/docs/components/command.zh-CN.md)。

## 许可证

基于 MIT 许可证发布，详见 [LICENSE](LICENSE)。
