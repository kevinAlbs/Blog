# This can be compiled by itself with `as --32`
# This is GNU assembler syntax (aka AT-T syntax) (src, dest)
# SNIPPET_BEGIN:trampoline
.section .text
.globl trampoline
.type trampoline, @function
trampoline:
# call hijacked. This pushes the address of the next instruction.
# when hijacked returns, we jump directly to the address in eax.
# eax contains the returned value of hijacked.
call hijacked # call hijacked
jmp %eax
# SNIPPET_END:trampoline