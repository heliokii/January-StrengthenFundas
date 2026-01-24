if __name__ == '__main__':
    n = int(input())
    arr = map(int, input().split())

    scores = list(set(arr))   # remove duplicates
    scores.sort(reverse=True) # sort descending

    print(scores[1])  