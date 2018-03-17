	.section	__TEXT,__text,regular,pure_instructions
	.macosx_version_min 10, 12
	.globl	_hijacked
	.p2align	4, 0x90
_hijacked:                              ## @hijacked
## BB#0:
	pushl	%ebp
	movl	%esp, %ebp
	subl	$8, %esp
	calll	L0$pb
L0$pb:
	popl	%eax
	leal	L_.str-L0$pb(%eax), %eax
	movl	%eax, (%esp)
	calll	_printf
	movl	%eax, -4(%ebp)          ## 4-byte Spill
	addl	$8, %esp
	popl	%ebp
	retl

	.globl	_f
	.p2align	4, 0x90
_f:                                     ## @f
## BB#0:
	pushl	%ebp
	movl	%esp, %ebp
	subl	$8, %esp
	calll	L1$pb
L1$pb:
	popl	%eax
	movl	8(%ebp), %ecx
	leal	_hijacked-L1$pb(%eax), %eax
	leal	-4(%ebp), %edx
	movl	%ecx, -4(%ebp)
	movl	%edx, -8(%ebp)
	movl	-8(%ebp), %ecx
	addl	$-4, %ecx
	movl	%ecx, -8(%ebp)
	movl	-8(%ebp), %ecx
	movl	%eax, (%ecx)
	addl	$8, %esp
	popl	%ebp
	retl

	.globl	_main
	.p2align	4, 0x90
_main:                                  ## @main
## BB#0:
	pushl	%ebp
	movl	%esp, %ebp
	subl	$24, %esp
	calll	L2$pb
L2$pb:
	popl	%eax
	movl	$57005, %ecx            ## imm = 0xDEAD
	movl	$57005, (%esp)          ## imm = 0xDEAD
	movl	%eax, -4(%ebp)          ## 4-byte Spill
	movl	%ecx, -8(%ebp)          ## 4-byte Spill
	calll	_f
	movl	-4(%ebp), %eax          ## 4-byte Reload
	leal	L_.str.1-L2$pb(%eax), %ecx
	movl	%ecx, (%esp)
	calll	_printf
	xorl	%ecx, %ecx
	movl	%eax, -12(%ebp)         ## 4-byte Spill
	movl	%ecx, %eax
	addl	$24, %esp
	popl	%ebp
	retl

	.section	__TEXT,__cstring,cstring_literals
L_.str:                                 ## @.str
	.asciz	"hijacked"

L_.str.1:                               ## @.str.1
	.asciz	"exiting main\n"


.subsections_via_symbols
