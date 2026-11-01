<p align="right">
  <a href="README.md">English</a> | <strong>中文文档</strong>
</p>

# SuperKernel Command

`super-kernel/command` 注册用本组件 `#[Command]` 注解声明的 Symfony Console 命令。命令按需延迟加载：只有当 Symfony 为执行、帮助或补全而初始化某个命令时，才会从共享容器中解析它。

## 注册命令

继承 Symfony 的 `Command`，并使用本组件的 final `Command` 注解。给 Symfony 基类起别名以避免名称冲突：

```php
use SuperKernel\Command\Attribute\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[Command(
    name: 'app:hello',
    description: 'Print a greeting.',
    aliases: ['hello'],
)]
final class HelloCommand extends SymfonyCommand
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Hello');

        return self::SUCCESS;
    }
}
```

这个 final readonly 注解只接受 `string $name`、`?string $description = null`、`array $aliases = []` 和 `bool $hidden = false`。`name` 是主名称，`aliases` 提供额外的名称，`hidden` 让命令不出现在列表中。

- 名称必填且不能为空；别名必须是由非空字符串组成的列表。
- 名称或别名重复（包括同一个声明内部的重复），以及类没有继承 Symfony 的 `Command` 时，注册会以 `InvalidArgumentException` 拒绝。这些错误会让注册失败，而不会变成跳过类的诊断；PHP 类型、目标和可重复性错误遵循注解扫描器的规则。
- 只有确切的类级 `#[Command]` 注解会注册命令。Symfony 的 `#[AsCommand]` 不是注册信号，也不支持方法级声明。

发现阶段不会实例化命令。列出命令以及为未知命令提示候选名称都不会解析依赖。当 Symfony 初始化选中的命令时，容器解析该类并校验结果；解析失败会向外传播。命令的身份和生命周期由容器负责。

Symfony 内置的 `help`、`list`、`completion` 和 `_complete` 由其 Application 提供。工具命令可以通过工具自己的可执行文件运行，例如 `php vendor/bin/php-cs-fixer check`，也可以通过一个带有本组件注解、使用该库公开服务的应用适配器运行。

默认的扫描日志按 DI 的诊断策略写到 stdout。需要机器可读 stdout 的应用应提供一个使用 stderr 传输的 stdout 日志器；Command 不会修改 Logger 的默认设置。

## Framework 集成

容器提供 Symfony 的 `CommandLoaderInterface`。当 Framework 的应用工厂分发 `SuperKernel\Event\BeforeApplicationStart` 时，如果事件中的应用是 `Symfony\Component\Console\Application`，本组件会把这个加载器挂到该应用上；其他 `ApplicationInterface` 实现保持不变。挂载加载器不会解析任何命令。
