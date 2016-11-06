## DevelNext jURL Bundle
Пакет расширений для работы с сетью c поддержкой curl_* функций

### Возможности
- Работа с куками
- Работа с прокси
- Отправка POST, PUT, DELETE запросов
- Загрузка файлов
- Изменение User-Agent, Referer
- Поддержка Basic авторизации ([пример](http://test.tssaltan.ru/curl/basic.php))
- Отображение ошибок и характеристик соединения

### Wiki
* **[Установка и обновление](https://github.com/TsSaltan/DevelNext-jURL/wiki/Установка)**
* **[Методы класса jURL](https://github.com/TsSaltan/DevelNext-jURL/wiki/Методы-класса-jURL)**
* **[Поддержка параметров cURL](https://github.com/TsSaltan/DevelNext-jURL/wiki/Поддержка-параметров-cURL)**
* **[Поддержка функций cURL](https://github.com/TsSaltan/DevelNext-jURL/wiki/Поддержка-функций-cURL)**
* **[Примеры](https://github.com/TsSaltan/DevelNext-jURL/wiki/Примеры)**
* **[Демо-проекты](https://github.com/TsSaltan/DevelNext-jURL/wiki/Демо)**
* **[Тема на форуме](http://community.develstudio.org/showthread.php/13145-cURL-в-DevelNext)**

### Changelog
```
--- 1.0.2 ---
[Fix] Ошибка с отправкой raw post
[Fix] Exception при получении кода 404
[Add] В компоненте загрузчик добавлено отображение имени скачиваемого файла

--- 1.0.1 ---
[Fix] Ошибка, возникающая если перед скачиванием файлов был редирект
[Fix] Заголовки не выводятся в файл при setReturnHeaders(true)
[Fix] Ошибка при установке/удалении пакета, если отсутствовал файл .bootstrap

--- 1.0 ---
[Add] Компонент загрузчик
[Add] Многопоточная загрузка
[Add] Добавлена поддержка функций http_build_query, parse_str
[Add] Метод reset для сброса параметров (curl_reset)
[Fix] Исправлена одновременная отправка файлов и переменных методом POST
[Fix] Прочие исправления

--- 0.6 ---
[Add] Загрузка только заголовков без тела запроса (cURL - CURLOPT_NOBODY; jURL - setReturnBody)
[Fix] Скачаный файл заблокирован процессом
[Fix] Ошибки при установке некорректных и неподдерживаемых параметров CURLOPT_*
[Change] В случае ошибки jURL выбрасывает jURLException

--- 0.5 ---
[Change] Модуль переделан в пакет расширений

--- 0.4.0.1 ---
[Fix] Ошибка при подключении модуля к форме

--- 0.4 ---
[Fix] Компилируются в байт-код все компоненты модуля
[Fix] Исправление ошибок

--- 0.3.1 ---
[Add] Добавлены подсказки
[Fix] Исправлен баг, из-за которого прогресс загрузки мог не дойти до 100%

--- 0.3 ---
[Add] Добавлены параметры CURLOPT_POST, CURLOPT_GET, CURLOPT_PUT, CURLOPT_INFILE
```

### Сборка расширения
#### Windows
```
gradlew.bat bundle
```

#### Linux
```
gradlew
```
