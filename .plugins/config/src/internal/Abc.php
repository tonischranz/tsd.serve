<?php
namespace tsd\serve\config\internal;

/**
 * @Default
 */
class Abc{

}


interface Xyz
{

}

/**
 * @Mode xyz
 */
class A extends Abc implements Xyz
{

}

/**
 * @Mode asdf
 */
class B extends A
{

}