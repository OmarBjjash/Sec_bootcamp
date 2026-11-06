#include <stdio.h>
#include <string.h>


static void show_help(const char *name)
{
    printf("usage: %s <file>\n\n", name);
    printf("Dumps a binary file as hex + ASCII, 16 bytes per row.\n");
    printf("Handy for eyeballing file headers and magic bytes.\n\n");
    printf("options:\n");
    printf("  -h, --help    show this message and exit\n");
}

int main(int argc, char **argv)
{
    size_t line = 16;
    FILE *file_pointer;
    unsigned char buffer[line];
    size_t n, i;
    unsigned long offset = 0;

    if (argc != 2) {
        fprintf(stderr, "%s: need a file\n\n", argv[0]);
        show_help(argv[0]);
        return 1;
    }

    if (!strcmp(argv[1], "-h") || !strcmp(argv[1], "--help")) {
        show_help(argv[0]);
        return 0;
    }

    if ((file_pointer = fopen(argv[1], "rb")) == NULL) {
        perror(argv[1]);
        return 1;
    }

    while ((n = fread(buffer, 1, line, file_pointer)) > 0) {
        printf("%08lX  ", offset);

        for (i = 0; i < line; i++) {
            if (i < n)
                printf("%02X ", buffer[i]);
            else
                printf("   ");
            if (i == 7)
                putchar(' ');
        }

        putchar('|');
        for (i = 0; i < n; i++)
            putchar(buffer[i] >= 32 && buffer[i] <= 126 ? buffer[i] : '.');
        printf("|\n");

        offset += n;
    }

    if (ferror(file_pointer)) {
        perror("read");
        fclose(file_pointer);
        return 1;
    }
    fclose(file_pointer);
    return 0;
}