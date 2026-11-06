#include <stdio.h>
#include <string.h>
#include <ctype.h>

static const char *default_keywords[] = { "FAILED", "404", "DENIED", "ERROR" };

static void show_help(const char *name)
{
    printf("usage: %s <logfile> [keyword ...]\n\n", name);
    printf("Scans a log file for lines containing any of the given keywords.\n");
    printf("Matching is case-insensitive.\n");
    printf("If no keywords are passed, it looks for FAILED, 404, DENIED and ERROR.\n\n");
    printf("examples:\n");
    printf("  %s /var/log/auth.log\n", name);
    printf("  %s access.log 500 404 DENIED\n", name);
    printf("\noptions:\n");
    printf("  -h, --help    print this help and exit\n");
}

static int not_case_sensitive(const char *text, const char *word)
{
    size_t i, j, word_len = strlen(word);

    if (word_len == 0)
        return 1;

    for (i = 0; text[i]; i++) {
        for (j = 0; j < word_len; j++) {
            if (!text[i + j])
                return 0;
            if (tolower((unsigned char)text[i + j]) !=
                tolower((unsigned char)word[j]))
                break;
        }
        if (j == word_len)
            return 1;
    }
    return 0;
}

int main(int argc, char **argv)
{
    int buffer_size = 512;
    int max_keyword = 16;
    int i, keyword_count;
    const char **keyword;
    FILE *fp;
    char buf[buffer_size];
    unsigned long line_number = 0, alert_counter = 0;

    if (argc < 2) {
        fprintf(stderr, "error: missing log file\n\n");
        show_help(argv[0]);
        return 1;
    }

    if (!strcmp(argv[1], "-h") || !strcmp(argv[1], "--help")) {
        show_help(argv[0]);
        return 0;
    }

    if (argc > 2) {
        keyword = (const char **)&argv[2];
        keyword_count = argc - 2;
    } else {
        keyword = default_keywords;
        keyword_count = sizeof(default_keywords) / sizeof(default_keywords[0]);
    }

    if (keyword_count > max_keyword) {
        fprintf(stderr, "too many keywords (max %d)\n", max_keyword);
        return 1;
    }

    if ((fp = fopen(argv[1], "r")) == NULL) {
        perror(argv[1]);
        return 1;
    }

    while (fgets(buf, sizeof buf, fp)) {
        line_number++;

        for (i = 0; i < keyword_count; i++) {
            if (!not_case_sensitive(buf, keyword[i]))
                continue;

            alert_counter++;

            size_t n = strlen(buf);
            if (n && buf[n - 1] == '\n')
                buf[--n] = 0;

            printf("[ALERT] line %lu | %-8s | %s\n", line_number, keyword[i], buf);
            break;
        }
    }

    if (ferror(fp)) {
        perror("read");
        fclose(fp);
        return 1;
    }
    fclose(fp);

    printf("\n-- summary --\n");
    printf("file   : %s\n", argv[1]);
    printf("lines  : %lu\n", line_number);
    printf("alerts : %lu\n", alert_counter);

    return alert_counter ? 0 : 2;
}