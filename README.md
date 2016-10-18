SimpleORM
=========

Friends
-------
By default, when we're creating a model from array, each property is set via an
appropriate setter to ensure valid data. However, when loading mass amounts of
objects from DB, it can become costly. Therefore, a mechanism of **friend 
classes** has been introduced. If a **friend class** tries to create a model 
from an array, all setters are bypassed, and object data is simple initialized
from that array. 

By default, abstract mapper and model's own mapper are **friends**, so when 
you're loading data from DB via `find()` or `search()` functions, they bypass
validation. If you want some other class to behave the same (for example, 
another mapper returns a collection of your models), simply define `$_friends`
variable in your model class:

```php
class Model_Mymodel extends Model
{
    protected static $_friends = array(
        'Mapper_Othermodel'
    );
}
```

Please, do not abuse friends functionality, as it can compromise your data 
quality. It's not recommended to define friends classes other that other 
mappers that can return collection of your models. 

Transactions
------------
There are three method in mapper to support transactions: `beginTransaction()`, 
`commit($tid)` and `rollback($tid)`. However, using them is not as trivial as
related functions in low-level DB adapters. 

Let's say you've started a transaction and need to call something else, and only
then do a commit. But that something else (and it can be called from somewhere
else, not just from your transaction code) needs to perform a transaction of its
own. If that code simply makes a commit, your unfinished work will be committed
too, and you won't be able to rollback. Therefore, the mapper in this library
supports NESTED transactions. 

When you call `beginTransaction()`, it returns you a **transaction ID**. You 
will need to pass this **transaction ID** into `commit()` and `rollback()` 
methods. The first time you've called `beginTransaction()`, mapper actually 
starts a transaction, stores its ID and returns it to you. If you call it again,
the mapper knows you're already in transaction, will not start a new one and
will return you NULL. When you try to `commit()` with a NULL, mapper knows 
you're trying to commit a nested transaction, so it won't actually commit. And
only when you `commit()` with the original **transaction ID**, an actual commit
will be performed via DB adapter.
