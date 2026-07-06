<?php

declare(strict_types=1);

namespace Skir\Runtime;

enum TypeKind: string
{
    case Bool = 'bool';
    case Int32 = 'int32';
    case Int64 = 'int64';
    case Hash64 = 'hash64';
    case Float32 = 'float32';
    case Float64 = 'float64';
    case Timestamp = 'timestamp';
    case String = 'string';
    case Bytes = 'bytes';
    case Optional = 'optional';
    case Array = 'array';
    case Struct = 'struct';
    case Enum = 'enum';
}
