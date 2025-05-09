<?php

function randN($length)
{
    $chars = '0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function randLC($length)
{
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    return substr(str_shuffle($chars), 0, $length);
}

function randUC($length)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

function randULC($length)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    return substr(str_shuffle($chars), 0, $length);
}

function randNLC($length)
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function randNUC($length)
{
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}

function randNULC($length)
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    return substr(str_shuffle($chars), 0, $length);
}
